<?php
/** "Post a New Recipe" — the 5-section recipe form (also edits & deletes). */
require_once __DIR__ . '/includes/bootstrap.php';

$me  = require_login();
$uid = (int)$me['id'];
ensure_recipe_youtube_column();
ensure_recipe_time_columns();

// ---------------------------------------------------------------- Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_id'])) {
    csrf_check();
    $rid = (int)$_POST['delete_id'];
    $st = db()->prepare('SELECT * FROM recipes WHERE id = ?');
    $st->execute([$rid]);
    $rec = $st->fetch();
    if ($rec && ((int)$rec['user_id'] === $uid || is_admin())) {
        delete_upload($rec['image']);
        foreach (db()->query('SELECT media FROM recipe_steps WHERE recipe_id = ' . $rid) as $row) {
            delete_upload($row['media']);
        }
        db()->prepare('DELETE FROM recipe_ingredients WHERE recipe_id = ?')->execute([$rid]);
        db()->prepare('DELETE FROM recipe_steps WHERE recipe_id = ?')->execute([$rid]);
        db()->prepare("DELETE FROM comments WHERE target_type = 'recipe' AND target_id = ?")->execute([$rid]);
        db()->prepare("DELETE FROM reactions WHERE target_type = 'recipe' AND target_id = ?")->execute([$rid]);
        db()->prepare('UPDATE wall_posts SET shared_recipe_id = NULL WHERE shared_recipe_id = ?')->execute([$rid]);
        db()->prepare('DELETE FROM recipes WHERE id = ?')->execute([$rid]);
        flash('success', 'Recipe deleted.');
    } else {
        flash('error', 'You can only delete your own recipes.');
    }
    redirect('profile.php?u=' . urlencode($me['username']) . '&tab=recipes');
}

// ---------------------------------------------------------------- Edit mode?
$editId = (int)($_GET['id'] ?? 0);
$recipe = null;
$rIngredients = [];
$rSteps = [];
if ($editId) {
    $st = db()->prepare("SELECT * FROM recipes WHERE id = ? AND status = 'published'");
    $st->execute([$editId]);
    $recipe = $st->fetch();
    if (!$recipe || ((int)$recipe['user_id'] !== $uid && !is_admin())) {
        flash('error', 'You can only edit your own recipes.');
        redirect('recipes.php');
    }
    $st = db()->prepare(
        'SELECT ri.quantity, i.name FROM recipe_ingredients ri JOIN ingredients i ON i.id = ri.ingredient_id
          WHERE ri.recipe_id = ? ORDER BY ri.sort_order, ri.id'
    );
    $st->execute([$editId]);
    $rIngredients = $st->fetchAll();
    $st = db()->prepare('SELECT * FROM recipe_steps WHERE recipe_id = ? ORDER BY step_no');
    $st->execute([$editId]);
    $rSteps = $st->fetchAll();
    // master names for prefill
    foreach (['category_id' => 'categories', 'cuisine_id' => 'cuisines', 'origin_id' => 'origins'] as $col => $table) {
        $recipe[$table . '_name'] = '';
        if ($recipe[$col]) {
            $st = db()->prepare("SELECT name FROM `$table` WHERE id = ?");
            $st->execute([$recipe[$col]]);
            $recipe[$table . '_name'] = (string)$st->fetchColumn();
        }
    }
}

$errors = [];

// ---------------------------------------------------------------- Create / update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['delete_id'])) {
    csrf_check();
    $editId = (int)($_POST['edit_id'] ?? 0);

    $title    = trim($_POST['title'] ?? '');
    $story    = trim($_POST['story'] ?? '');
    $youtube  = trim($_POST['youtube'] ?? '');
    $verdict  = trim($_POST['verdict'] ?? '');
    $catName  = trim($_POST['category'] ?? '');
    $cuiName  = trim($_POST['cuisine'] ?? '');
    $oriName  = trim($_POST['origin'] ?? '');
    $prepTime = max(0, (int)($_POST['prep_time'] ?? 0)) ?: null;
    $cookTime = max(0, (int)($_POST['cook_time'] ?? 0)) ?: null;
    $ingNames = $_POST['ing_name'] ?? [];
    $ingQtys  = $_POST['ing_qty'] ?? [];
    $stepTexts = $_POST['step_text'] ?? [];
    $keepMedia = $_POST['keep_media'] ?? [];

    if ($title === '' || mb_strlen($title) > 150) {
        $errors[] = 'Recipe name is mandatory (max 150 characters).';
    }

    // optional YouTube link — accept only if it resolves to a real video id
    if ($youtube !== '' && youtube_id($youtube) === '') {
        $errors[] = 'That does not look like a valid YouTube link. Paste the full video URL, or leave it empty.';
    }

    // ingredients: keep rows where a name was entered
    $ingRows = [];
    foreach ((array)$ingNames as $i => $name) {
        $name = trim((string)$name);
        if ($name !== '') {
            $ingRows[] = ['name' => $name, 'qty' => trim((string)($ingQtys[$i] ?? ''))];
        }
    }
    if (!$ingRows) {
        $errors[] = 'Add at least one ingredient.';
    }

    // steps: keep rows with instructions (keys are explicit indices shared
    // with step_media[] and keep_media[], renumbered client-side)
    $stepTexts = (array)$stepTexts;
    ksort($stepTexts);
    $stepRows = [];
    foreach ($stepTexts as $i => $text) {
        $text = trim((string)$text);
        if ($text !== '') {
            $stepRows[] = ['text' => $text, 'file_idx' => $i, 'keep' => trim((string)($keepMedia[$i] ?? ''))];
        }
    }
    if (!$stepRows) {
        $errors[] = 'Describe at least one method step.';
    }

    // main picture (mandatory on create, optional replacement on edit)
    $mainImage = $recipe['image'] ?? null;
    if (!$errors) {
        try {
            $up = handle_upload('main_image', 'image', 'recipes');
            if ($up) {
                if ($mainImage) delete_upload($mainImage);
                $mainImage = $up['file'];
            } elseif (!$editId) {
                $errors[] = 'The main picture of the recipe is mandatory.';
            }
        } catch (RuntimeException $ex) {
            $errors[] = 'Main picture: ' . $ex->getMessage();
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $catId = $catName !== '' ? find_or_create('categories', $catName, $uid) : null;
            $cuiId = $cuiName !== '' ? find_or_create('cuisines', $cuiName, $uid) : null;
            $oriId = $oriName !== '' ? find_or_create('origins', $oriName, $uid) : null;

            if ($editId) {
                $pdo->prepare(
                    'UPDATE recipes SET title=?, image=?, story=?, youtube_url=?, prep_time=?, cook_time=?, category_id=?, cuisine_id=?, origin_id=?, verdict=? WHERE id=?'
                )->execute([$title, $mainImage, $story ?: null, $youtube ?: null, $prepTime, $cookTime, $catId, $cuiId, $oriId, $verdict ?: null, $editId]);
                $recipeId = $editId;
                $pdo->prepare('DELETE FROM recipe_ingredients WHERE recipe_id = ?')->execute([$recipeId]);
                // remove old step media that is not being kept
                $kept = array_filter(array_map(fn($s) => $s['keep'], $stepRows));
                $st = $pdo->prepare('SELECT media FROM recipe_steps WHERE recipe_id = ? AND media IS NOT NULL');
                $st->execute([$recipeId]);
                foreach ($st as $row) {
                    if (!in_array($row['media'], $kept, true)) delete_upload($row['media']);
                }
                $pdo->prepare('DELETE FROM recipe_steps WHERE recipe_id = ?')->execute([$recipeId]);
            } else {
                $slug = slugify($title);
                $st = $pdo->prepare('SELECT COUNT(*) FROM recipes WHERE slug LIKE ?');
                $st->execute([$slug . '%']);
                if ((int)$st->fetchColumn() > 0) $slug .= '-' . substr(bin2hex(random_bytes(3)), 0, 5);
                $pdo->prepare(
                    "INSERT INTO recipes (user_id, title, slug, image, story, youtube_url, prep_time, cook_time, category_id, cuisine_id, origin_id, verdict, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', NOW())"
                )->execute([$uid, $title, $slug, $mainImage, $story ?: null, $youtube ?: null, $prepTime, $cookTime, $catId, $cuiId, $oriId, $verdict ?: null]);
                $recipeId = (int)$pdo->lastInsertId();
            }

            $insIng = $pdo->prepare(
                'INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, sort_order) VALUES (?, ?, ?, ?)'
            );
            $seen = [];
            foreach ($ingRows as $order => $row) {
                $ingId = find_or_create('ingredients', $row['name'], $uid);
                if ($ingId && !isset($seen[$ingId])) {
                    $seen[$ingId] = true;
                    $insIng->execute([$recipeId, $ingId, mb_substr($row['qty'], 0, 80), $order]);
                }
            }

            $insStep = $pdo->prepare(
                'INSERT INTO recipe_steps (recipe_id, step_no, instruction, media, media_type) VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($stepRows as $n => $row) {
                $media = $row['keep'] !== '' ? $row['keep'] : null;
                $mediaType = null;
                if ($media) {
                    $mediaType = preg_match('/\.(mp4|webm|mov)$/i', $media) ? 'video' : 'image';
                }
                // uploaded file for this step? ($_FILES['step_media'] is an array-field)
                $idx = $row['file_idx'];
                if (!empty($_FILES['step_media']['name'][$idx])) {
                    $_FILES['__step_tmp'] = [
                        'name'     => $_FILES['step_media']['name'][$idx],
                        'type'     => $_FILES['step_media']['type'][$idx],
                        'tmp_name' => $_FILES['step_media']['tmp_name'][$idx],
                        'error'    => $_FILES['step_media']['error'][$idx],
                        'size'     => $_FILES['step_media']['size'][$idx],
                    ];
                    $up = handle_upload('__step_tmp', 'media', 'steps');
                    if ($up) {
                        if ($media) delete_upload($media);
                        $media = $up['file'];
                        $mediaType = $up['type'];
                    }
                }
                $insStep->execute([$recipeId, $n + 1, $row['text'], $media, $mediaType]);
            }

            $pdo->commit();
            flash('success', $editId ? 'Recipe updated! 🌟' : 'Your recipe is live! 🎉 Share it with your buddies.');
            // Send email notifications for new recipes only
            if (!$editId) {
                send_recipe_notification_emails($recipeId, $uid);
            }
            redirect('recipe.php?id=' . $recipeId);
        } catch (RuntimeException $ex) {
            $pdo->rollBack();
            $errors[] = $ex->getMessage();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            $errors[] = 'Something went wrong while saving — please try again.';
        }
    }
}

$pageTitle = $recipe ? 'Edit Recipe' : 'Post a New Recipe';
include __DIR__ . '/includes/header.php';

// prefill values (POST wins over edit data)
$v = fn(string $key, string $fallback = '') => e($_POST[$key] ?? ($recipe[$key] ?? $fallback));
?>
<div class="container section" style="max-width:860px">
  <h1><?= $recipe ? '✏️ Edit Recipe' : '🍳 Post a New Recipe' ?></h1>
  <p class="muted">Share your fusion creation with the community. Fields marked <span style="color:var(--orange-600)">*</span> are mandatory — everything else is optional.</p>

  <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>

  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php if ($recipe): ?><input type="hidden" name="edit_id" value="<?= (int)$recipe['id'] ?>"><?php endif; ?>

    <!-- Section 1: basics -->
    <div class="panel">
      <h3><span class="panel-section-no">1</span>The recipe &amp; its story</h3>
      <div class="form-grid">
        <label class="field">Name of the recipe <span class="req">*</span>
          <input type="text" name="title" required maxlength="150" value="<?= $v('title') ?>" placeholder="e.g. Paneer Tikka Quesadilla">
        </label>
        <label class="field">Main picture of the recipe <?= $recipe ? '<small>Leave empty to keep the current photo</small>' : '<span class="req">*</span>' ?>
          <input type="file" name="main_image" accept="image/jpeg,image/png,image/webp,image/gif" <?= $recipe ? '' : 'required' ?>>
          <?php if ($recipe && $recipe['image']): ?>
            <img src="<?= e(url($recipe['image'])) ?>" alt="" style="max-height:120px;border-radius:10px;margin-top:8px">
          <?php endif; ?>
          <span class="form-help">JPG, PNG, WEBP or GIF — max 5 MB. Make it delicious! 📸</span>
        </label>
        <label class="field">Short description / story behind it <small>Optional — where did this fusion idea come from?</small>
          <textarea name="story" maxlength="3000" placeholder="The story behind this dish…"><?= $v('story') ?></textarea>
        </label>
        <label class="field">YouTube video link <small>Optional — paste the link to a video of this recipe. It will play at the top of your recipe page.</small>
          <input type="url" name="youtube" maxlength="255" inputmode="url"
                 value="<?= e($_POST['youtube'] ?? ($recipe['youtube_url'] ?? '')) ?>"
                 placeholder="e.g. https://www.youtube.com/watch?v=…">
        </label>
      </div>
    </div>

    <!-- Section 2: ingredients -->
    <div class="panel">
      <h3><span class="panel-section-no">2</span>Ingredients &amp; quantities <span class="req">*</span></h3>
      <p class="form-help" style="margin-top:-8px">Type the first 3 letters to search existing ingredients. Can't find yours? Just keep typing — it will be added to the master list for everyone. 🌶️</p>
      <div id="ingredient-rows">
        <?php
        $prefIng = [];
        if (isset($_POST['ing_name'])) {
            foreach ((array)$_POST['ing_name'] as $i => $n) {
                if (trim($n) !== '') $prefIng[] = ['name' => $n, 'quantity' => $_POST['ing_qty'][$i] ?? ''];
            }
        } elseif ($rIngredients) {
            $prefIng = $rIngredients;
        }
        if (!$prefIng) $prefIng = [['name' => '', 'quantity' => '']];
        foreach ($prefIng as $row): ?>
        <div class="repeat-row">
          <div class="ta-wrap">
            <input type="text" class="typeahead" data-master="ingredients" name="ing_name[]" maxlength="100"
                   placeholder="Ingredient (e.g. Paneer)" value="<?= e($row['name']) ?>" autocomplete="off">
          </div>
          <input type="text" class="qty" name="ing_qty[]" maxlength="80" placeholder="Quantity (e.g. 200 g)" value="<?= e($row['quantity']) ?>">
          <button type="button" class="row-remove" title="Remove ingredient">✕</button>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-ghost btn-sm" id="add-ingredient">+ Add another ingredient</button>
    </div>

    <!-- Section 3: general info -->
    <div class="panel">
      <h3><span class="panel-section-no">3</span>General information <small class="muted" style="font-family:var(--font-body);font-size:13px">(optional)</small></h3>
      <p class="form-help" style="margin-top:-8px">Same magic here — type 3 letters to search, or enter a new value to add it to the master lists.</p>
      <div class="form-row">
        <label class="field">Category
          <span class="ta-wrap"><input type="text" class="typeahead" data-master="categories" name="category" maxlength="100"
                 placeholder="e.g. Main Course" value="<?= e($_POST['category'] ?? ($recipe['categories_name'] ?? '')) ?>" autocomplete="off"></span>
        </label>
        <label class="field">Cuisine type
          <span class="ta-wrap"><input type="text" class="typeahead" data-master="cuisines" name="cuisine" maxlength="100"
                 placeholder="e.g. Indo-Mexican" value="<?= e($_POST['cuisine'] ?? ($recipe['cuisines_name'] ?? '')) ?>" autocomplete="off"></span>
        </label>
      </div>
      <label class="field" style="margin-top:14px">Origin
        <span class="ta-wrap"><input type="text" class="typeahead" data-master="origins" name="origin" maxlength="100"
               placeholder="e.g. Mumbai street food" value="<?= e($_POST['origin'] ?? ($recipe['origins_name'] ?? '')) ?>" autocomplete="off"></span>
      </label>
      <div class="form-row" style="margin-top:14px">
        <label class="field">Prep time <small>Optional — in minutes</small>
          <input type="number" name="prep_time" min="0" max="9999" placeholder="e.g. 30"
                 value="<?= e($_POST['prep_time'] ?? ($recipe['prep_time'] ?? '')) ?>">
        </label>
        <label class="field">Cook time <small>Optional — in minutes</small>
          <input type="number" name="cook_time" min="0" max="9999" placeholder="e.g. 45"
                 value="<?= e($_POST['cook_time'] ?? ($recipe['cook_time'] ?? '')) ?>">
        </label>
      </div>
    </div>

    <!-- Section 4: method steps -->
    <div class="panel">
      <h3><span class="panel-section-no">4</span>Step-by-step method <span class="req">*</span></h3>
      <div id="step-blocks">
        <?php
        $prefSteps = [];
        if (isset($_POST['step_text'])) {
            foreach ((array)$_POST['step_text'] as $i => $t) {
                if (trim($t) !== '') $prefSteps[] = ['instruction' => $t, 'media' => $_POST['keep_media'][$i] ?? null, 'media_type' => null];
            }
        } elseif ($rSteps) {
            $prefSteps = $rSteps;
        }
        if (!$prefSteps) $prefSteps = [['instruction' => '', 'media' => null, 'media_type' => null]];
        foreach ($prefSteps as $i => $s): ?>
        <div class="step-block">
          <div class="step-block-head">
            <span class="step-label">Step <?= $i + 1 ?></span>
            <button type="button" class="btn btn-sm btn-danger step-remove">Remove step</button>
          </div>
          <textarea name="step_text[<?= $i ?>]" maxlength="4000" placeholder="Describe this step…"><?= e($s['instruction']) ?></textarea>
          <?php if (!empty($s['media'])): ?>
            <div class="step-existing form-help">📎 Current media kept: <?= e(basename($s['media'])) ?> (upload a new file below to replace it)</div>
            <input type="hidden" class="keep-media" name="keep_media[<?= $i ?>]" value="<?= e($s['media']) ?>">
          <?php endif; ?>
          <label class="field" style="margin-top:10px">Step photo or short video <small>Optional — image max 5 MB, video max 25 MB</small>
            <input type="file" name="step_media[<?= $i ?>]" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime">
          </label>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-ghost btn-sm" id="add-step">+ Add next step</button>
    </div>

    <!-- Section 5: verdict -->
    <div class="panel">
      <h3><span class="panel-section-no">5</span>Verdict, secrets &amp; trivia <small class="muted" style="font-family:var(--font-body);font-size:13px">(optional)</small></h3>
      <label class="field">Overall verdict / hidden information / special instructions / trivia
        <textarea name="verdict" maxlength="3000" placeholder="Secret tip: rest the dough 20 minutes… or some fun trivia about the dish!"><?= $v('verdict') ?></textarea>
      </label>
    </div>

    <div style="text-align:center;margin:30px 0">
      <button class="btn btn-accent" type="submit" style="font-size:17px;padding:14px 44px">
        <?= $recipe ? '💾 Save changes' : '🚀 Post Recipe' ?>
      </button>
    </div>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
