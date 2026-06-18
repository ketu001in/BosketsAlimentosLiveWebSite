# -*- coding: utf-8 -*-
"""Generate a PDF report: Top Book Publishers in Bangalore for a first-time author."""
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_CENTER, TA_LEFT, TA_JUSTIFY
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak,
    HRFlowable, KeepTogether, ListFlowable, ListItem
)

OUT = r"D:\Ketul\Claude_Projects\BosketsAlimentosWebSite\Bangalore_Book_Publishers_Report.pdf"

# ---- palette ----
INK      = colors.HexColor("#1d2733")
SLATE    = colors.HexColor("#44515f")
ACCENT   = colors.HexColor("#0f6b5c")   # deep teal/green
ACCENT_L = colors.HexColor("#e3f1ee")
GOLD     = colors.HexColor("#b8860b")
LINE     = colors.HexColor("#d4dae0")
ZEBRA    = colors.HexColor("#f4f7f8")
HEADBG   = colors.HexColor("#0f6b5c")

styles = getSampleStyleSheet()

def S(name, **kw):
    base = kw.pop("parent", styles["Normal"])
    return ParagraphStyle(name, parent=base, **kw)

title_st   = S("t",  fontName="Helvetica-Bold", fontSize=26, leading=30, textColor=INK)
subtitle_st= S("st", fontName="Helvetica",      fontSize=12.5, leading=17, textColor=SLATE)
h1         = S("h1", fontName="Helvetica-Bold", fontSize=15, leading=19, textColor=ACCENT,
               spaceBefore=14, spaceAfter=6)
h2         = S("h2", fontName="Helvetica-Bold", fontSize=12, leading=15, textColor=INK,
               spaceBefore=10, spaceAfter=4)
body       = S("body", fontName="Helvetica", fontSize=9.7, leading=14.5, textColor=INK,
               alignment=TA_JUSTIFY, spaceAfter=5)
body_l     = S("body_l", parent=body, alignment=TA_LEFT)
small      = S("small", fontName="Helvetica", fontSize=8.2, leading=11.5, textColor=SLATE)
small_i    = S("small_i", parent=small, fontName="Helvetica-Oblique")
cell       = S("cell", fontName="Helvetica", fontSize=8.2, leading=10.8, textColor=INK)
cell_b     = S("cell_b", parent=cell, fontName="Helvetica-Bold")
cell_w     = S("cell_w", parent=cell, textColor=colors.white, fontName="Helvetica-Bold", fontSize=8.4)
tag        = S("tag", fontName="Helvetica-Bold", fontSize=8, leading=10, textColor=ACCENT)

story = []

# ============ COVER ============
story.append(Spacer(1, 40))
story.append(Paragraph("Publishing Your First Book", subtitle_st))
story.append(Spacer(1, 6))
story.append(Paragraph("Top 10 Genuine &amp; Reasonable<br/>Book Publishers in Bangalore", title_st))
story.append(Spacer(1, 10))
story.append(HRFlowable(width="100%", thickness=2, color=ACCENT))
story.append(Spacer(1, 10))
story.append(Paragraph(
    "A research-backed buyer's guide for a first-time author who wants "
    "<b>minimum investment</b> and <b>maximum reach</b> &mdash; with contact details, "
    "package pricing, royalty structures, a side-by-side comparison, and a recommended action plan.",
    subtitle_st))
story.append(Spacer(1, 24))

meta_tbl = Table([
    ["Prepared for", "First-time Author (Bangalore)"],
    ["Focus", "Self-publishing & print-on-demand"],
    ["Date", "June 2026"],
    ["Coverage", "10 publishers + decision framework"],
], colWidths=[35*mm, 120*mm])
meta_tbl.setStyle(TableStyle([
    ("FONTNAME",(0,0),(0,-1),"Helvetica-Bold"),
    ("FONTNAME",(1,0),(1,-1),"Helvetica"),
    ("FONTSIZE",(0,0),(-1,-1),9.5),
    ("TEXTCOLOR",(0,0),(0,-1),ACCENT),
    ("TEXTCOLOR",(1,0),(1,-1),INK),
    ("TOPPADDING",(0,0),(-1,-1),5),
    ("BOTTOMPADDING",(0,0),(-1,-1),5),
    ("LINEBELOW",(0,0),(-1,-2),0.4,LINE),
    ("VALIGN",(0,0),(-1,-1),"MIDDLE"),
]))
story.append(meta_tbl)
story.append(Spacer(1, 26))
story.append(HRFlowable(width="100%", thickness=0.6, color=LINE))
story.append(Spacer(1, 6))
story.append(Paragraph(
    "<b>How to read this report:</b> &ldquo;Genuine &amp; reasonable&rdquo; here means transparent pricing, "
    "the author keeps their rights and ISBN, no inflated &lsquo;vanity-press&rsquo; fees, and real distribution. "
    "Prices and royalties below are indicative (as published online, June 2026) and should be "
    "re-confirmed with each publisher before you pay. Always insist on a written quote and contract.",
    small))
story.append(PageBreak())

# ============ EXECUTIVE SUMMARY ============
story.append(Paragraph("1. Executive Summary &mdash; What a First-Time Author Should Know", h1))
story.append(Paragraph(
    "For a debut author, the single biggest decision is <b>self-publishing</b> vs. a traditional "
    "publishing contract. Traditional houses (Penguin, HarperCollins, etc.) pay <i>you</i> and cost "
    "nothing, but they accept a tiny fraction of unsolicited manuscripts and take 12&ndash;24 months. "
    "<b>Self-publishing / print-on-demand (POD)</b> is the realistic route for &lsquo;minimum investment, "
    "maximum reach&rsquo; today: you can be live on Amazon and Flipkart within weeks.", body))

story.append(Paragraph(
    "The smartest low-budget strategy is a <b>hybrid</b>: do the free distribution yourself on "
    "Amazon KDP (global reach, zero cost) and pay a Bangalore publisher only for the services you "
    "genuinely can&rsquo;t do yourself &mdash; professional editing and a strong cover. Below is a quick "
    "&lsquo;value map&rsquo; of the three approaches.", body))

vm = [
    [Paragraph("Approach", cell_w), Paragraph("Typical Cost", cell_w),
     Paragraph("Reach", cell_w), Paragraph("Best for", cell_w)],
    [Paragraph("DIY platforms<br/>(KDP, Pothi, Pratilipi)", cell_b),
     Paragraph("&#8377;0 &ndash; small printing cost", cell),
     Paragraph("Global (KDP) /<br/>National", cell),
     Paragraph("Tightest budgets, tech-comfortable authors", cell)],
    [Paragraph("Assisted self-publishing<br/>(Notion Press, Clever Fox, BFC, BlueRose, Zorba)", cell_b),
     Paragraph("&#8377;2,000 &ndash; &#8377;50,000+ (package)", cell),
     Paragraph("National +<br/>online global", cell),
     Paragraph("Want hand-holding: editing, cover, ISBN, listing done for you", cell)],
    [Paragraph("Traditional publishing", cell_b),
     Paragraph("&#8377;0 (they pay you)", cell),
     Paragraph("Bookstores +<br/>online", cell),
     Paragraph("Authors who can wait 1&ndash;2 yrs &amp; survive heavy rejection", cell)],
]
t = Table(vm, colWidths=[52*mm, 38*mm, 27*mm, 53*mm])
t.setStyle(TableStyle([
    ("BACKGROUND",(0,0),(-1,0),HEADBG),
    ("ROWBACKGROUNDS",(0,1),(-1,-1),[colors.white, ZEBRA]),
    ("GRID",(0,0),(-1,-1),0.4,LINE),
    ("VALIGN",(0,0),(-1,-1),"MIDDLE"),
    ("TOPPADDING",(0,0),(-1,-1),6),
    ("BOTTOMPADDING",(0,0),(-1,-1),6),
    ("LEFTPADDING",(0,0),(-1,-1),7),
    ("RIGHTPADDING",(0,0),(-1,-1),7),
]))
story.append(Spacer(1, 4))
story.append(t)
story.append(Spacer(1, 6))
story.append(Paragraph(
    "&#9656; <b>Bottom line for you:</b> Start with <b>Amazon KDP</b> for free global reach, "
    "and optionally add <b>Pothi.com</b> (Bangalore, POD for Flipkart/Amazon India). "
    "If you want it all done for you, <b>Notion Press</b> and <b>Clever Fox</b> offer the best "
    "value-for-money assisted packages. Full reasoning in Section 4.", small_i))

story.append(PageBreak())

# ============ COMPARISON TABLE ============
story.append(Paragraph("2. Side-by-Side Comparison (Top 10)", h1))
story.append(Paragraph(
    "Sorted roughly from lowest to higher investment. &lsquo;BLR&rsquo; = head office in Bengaluru; "
    "others are national/online platforms fully usable from Bangalore.", small))
story.append(Spacer(1, 4))

head = [Paragraph(x, cell_w) for x in
        ["#", "Publisher", "Base", "Entry Cost", "Royalty to Author", "Reach"]]
rows = [
    ["1","Amazon KDP","Global / online","Free","Ebook 35&ndash;70%; Paperback 60% &minus; print cost","Global (best)"],
    ["2","Pothi.com","Bengaluru","Free (POD)","High; via price calc.","India + Amazon/Flipkart"],
    ["3","Pratilipi","Bengaluru","Free","Revenue share","Huge online, 12 Indian langs"],
    ["4","Notion Press","Chennai","Free express; pkgs ~&#8377;3,000+","Up to 70% (site); ~8% Amazon","National + global online"],
    ["5","Clever Fox","Bengaluru","From &#8377;2,500","Up to 100% (you keep profit)","National + online"],
    ["6","BlueRose","Delhi/Noida +UK","From &#8377;1,990","100% to author","Worldwide online"],
    ["7","Zorba Books","Gurugram","Custom package","60&ndash;70%","National + online"],
    ["8","BFC Publications","Bengaluru","Tiered packages","High (package based)","National + online"],
    ["9","Punya Publishing","Bengaluru","Quote-based","Negotiated","Regional + national"],
    ["10","Pustak Mahal","Bengaluru branch","Low-cost paperback","Trade terms","Mass-market + Kindle"],
]
data = [head]
for r in rows:
    data.append([Paragraph(r[0], cell_b), Paragraph(r[1], cell_b)] + [Paragraph(x, cell) for x in r[2:]])
ct = Table(data, colWidths=[7*mm, 30*mm, 25*mm, 33*mm, 40*mm, 35*mm], repeatRows=1)
ct.setStyle(TableStyle([
    ("BACKGROUND",(0,0),(-1,0),HEADBG),
    ("ROWBACKGROUNDS",(0,1),(-1,-1),[colors.white, ZEBRA]),
    ("GRID",(0,0),(-1,-1),0.4,LINE),
    ("VALIGN",(0,0),(-1,-1),"MIDDLE"),
    ("TOPPADDING",(0,0),(-1,-1),5),
    ("BOTTOMPADDING",(0,0),(-1,-1),5),
    ("LEFTPADDING",(0,0),(-1,-1),5),
    ("RIGHTPADDING",(0,0),(-1,-1),5),
    ("ALIGN",(0,0),(0,-1),"CENTER"),
]))
story.append(ct)
story.append(Spacer(1, 8))
story.append(Paragraph(
    "Note: Notion Press&rsquo;s ~8% figure is the royalty on copies sold <i>through Amazon/Flipkart</i> on its "
    "free plan; royalty on its own store is higher (~30%). &lsquo;Up to 100%&rsquo; (Clever Fox/BlueRose) means "
    "you keep the full profit after printing &amp; distribution costs are deducted from the MRP &mdash; "
    "compare the <i>net rupees per copy</i>, not just the percentage.", small_i))
story.append(PageBreak())

# ============ DETAILED PROFILES ============
story.append(Paragraph("3. Detailed Publisher Profiles", h1))
story.append(Paragraph(
    "Each profile lists contact details, what it costs, what you get, and an honest &lsquo;best for / "
    "watch-outs&rsquo; note. Contact details are from each company&rsquo;s public website/listings; "
    "verify before paying.", small))
story.append(Spacer(1, 6))

def profile(num, name, badge, contact_rows, pricing, services, best_for, watch):
    blocks = []
    # header bar
    hb = Table([[Paragraph(f"{num}. {name}", S('pn', fontName='Helvetica-Bold',
                fontSize=12, textColor=colors.white)),
                 Paragraph(badge, S('pb', fontName='Helvetica-Bold', fontSize=8,
                textColor=colors.white, alignment=2))]],
               colWidths=[120*mm, 35*mm])
    hb.setStyle(TableStyle([
        ("BACKGROUND",(0,0),(-1,-1),ACCENT),
        ("VALIGN",(0,0),(-1,-1),"MIDDLE"),
        ("TOPPADDING",(0,0),(-1,-1),5),("BOTTOMPADDING",(0,0),(-1,-1),5),
        ("LEFTPADDING",(0,0),(-1,-1),8),("RIGHTPADDING",(0,0),(-1,-1),8),
    ]))
    blocks.append(hb)
    # contact block
    cdata = [[Paragraph(f"<b>{k}</b>", cell), Paragraph(v, cell)] for k,v in contact_rows]
    cttbl = Table(cdata, colWidths=[26*mm, 129*mm])
    cttbl.setStyle(TableStyle([
        ("BACKGROUND",(0,0),(-1,-1),ACCENT_L),
        ("VALIGN",(0,0),(-1,-1),"TOP"),
        ("TOPPADDING",(0,0),(-1,-1),3),("BOTTOMPADDING",(0,0),(-1,-1),3),
        ("LEFTPADDING",(0,0),(-1,-1),8),("RIGHTPADDING",(0,0),(-1,-1),8),
        ("LINEBELOW",(0,0),(-1,-2),0.3,colors.white),
    ]))
    blocks.append(cttbl)
    blocks.append(Spacer(1,4))
    grid = [
        [Paragraph("Pricing", cell_b), Paragraph(pricing, cell)],
        [Paragraph("Services", cell_b), Paragraph(services, cell)],
        [Paragraph("Best for", cell_b), Paragraph(best_for, cell)],
        [Paragraph("Watch-outs", cell_b), Paragraph(watch, cell)],
    ]
    gt = Table(grid, colWidths=[26*mm, 129*mm])
    gt.setStyle(TableStyle([
        ("VALIGN",(0,0),(-1,-1),"TOP"),
        ("TOPPADDING",(0,0),(-1,-1),4),("BOTTOMPADDING",(0,0),(-1,-1),4),
        ("LEFTPADDING",(0,0),(-1,-1),8),("RIGHTPADDING",(0,0),(-1,-1),8),
        ("LINEBELOW",(0,0),(-1,-2),0.3,LINE),
        ("BACKGROUND",(0,3),(0,3),colors.HexColor('#fdf3e3')),
    ]))
    blocks.append(gt)
    blocks.append(Spacer(1,12))
    return KeepTogether(blocks)

story.append(profile(
    1, "Amazon KDP (Kindle Direct Publishing)", "FREE &middot; GLOBAL",
    [("Website","kdp.amazon.com  /  kdp.amazon.in"),
     ("Support","Online help centre &amp; email (no India phone line)"),
     ("Mode","100% self-service, online")],
    "Free to publish ebook &amp; paperback. You only pay (indirectly) the per-copy printing cost, "
    "deducted at sale. No package fee.",
    "Kindle ebook + print-on-demand paperback, global distribution, KDP Select promos, sales dashboard. "
    "You arrange your own editing/cover.",
    "Authors who want the widest possible reach at zero upfront cost and are comfortable uploading files themselves.",
    "Ebook royalty is 70% only in the $2.99&ndash;$9.99 band (else 35%); India marketplace can be lower. "
    "No editing/design help &mdash; quality is on you. This should usually be <b>part</b> of your plan regardless."
))

story.append(profile(
    2, "Pothi.com (Mudranik Technologies Pvt Ltd)", "BLR &middot; FREE POD",
    [("Address","Ground Floor, No. 46, 11th Cross, Indiranagar 1st Stage, Bengaluru 560038"),
     ("Email","info@pothi.com  (no phone support &mdash; use contact form)"),
     ("Founded","2008 &middot; 18,000+ books published")],
    "Free to publish (POD). Pay only printing per copy; premium distribution plans charge printing "
    "upfront, reimbursed monthly from royalties. Use their price &amp; royalty calculator.",
    "Print-on-demand from 1 to 10,000 copies, no inventory, sell on Pothi.com + Amazon.in + Flipkart, "
    "ISBN, optional paid editing/design add-ons.",
    "Budget-first authors who want an India-based, transparent POD partner and good per-copy economics.",
    "Self-service oriented &mdash; limited hand-holding. No phone line; email/form support only."
))

story.append(profile(
    3, "Pratilipi", "BLR &middot; FREE",
    [("Address","Bengaluru, Karnataka (online platform)"),
     ("Website","pratilipi.com"),
     ("Since","2014 &middot; 12 Indian languages")],
    "Free to publish. Earnings via revenue-share / reader engagement programs.",
    "Largest Indian-language online self-publishing &amp; reading community; serialised stories, poetry, "
    "novels; built-in audience and discovery.",
    "Regional-language authors and those who want to build a readership/fanbase online before/with a print book.",
    "Primarily a digital reading platform, not a print/bookstore route. Best used <i>alongside</i> a print option, "
    "not as your only channel if you want a physical book."
))

story.append(profile(
    4, "Notion Press", "PAN-INDIA &middot; POPULAR",
    [("Address","#7, Red Cross Road, Egmore, Chennai 600008"),
     ("Phone / Email","+91 44 4252 4252  &middot;  via notionpress.com/contact"),
     ("Note","India&rsquo;s best-known self-publishing brand")],
    "Free &lsquo;Express&rsquo; publishing route; paid guided packages from roughly &#8377;3,000 upward "
    "(ISBN + Amazon/Flipkart listing). Higher tiers add editing, design &amp; marketing.",
    "Print + ebook, ISBN, cover &amp; interior design, global online distribution, author dashboard, "
    "marketing add-ons.",
    "First-timers who want a polished, well-known platform with optional done-for-you help.",
    "Marketplace royalty on the free plan is low (~8% on Amazon/Flipkart vs ~30% on their own store). "
    "Read which channel pays what before pricing your book."
))

story.append(profile(
    5, "Clever Fox Publishing", "BLR &middot; 100% ROYALTY",
    [("Address","No. 14, Rama Krishna Nagar, Kanakapura Main Rd, Opp Pillar 71, Sarakki Signal, "
      "Bengaluru 560078"),
     ("Phone","+91 93537 91933 (India/Intl) &middot; +44 7700 141933 (UK)"),
     ("Royalty","100% &mdash; author keeps profit after costs")],
    "Quick Publish &#8377;3,000; Clay Print &#8377;7,999; Papyrus &#8377;11,999; Bamboo &#8377;21,999; "
    "Bamboo Select &#8377;29,999; Wood &#8377;52,999. Basic plans from ~&#8377;2,500.",
    "Editing, cover &amp; interior design, ISBN, paperback + ebook, Amazon/Flipkart distribution, "
    "AI author dashboard (royalty &amp; sales tracking), marketing tiers.",
    "Bangalore authors who want a clearly-priced package ladder and to keep 100% of net royalty.",
    "Higher tiers get expensive &mdash; match the package to what you actually need; don&rsquo;t over-buy marketing."
))

story.append(profile(
    6, "BlueRose Publishers", "PAN-INDIA &middot; LOW ENTRY",
    [("Offices","Noida (B-6, ABL Workspaces, Sector 4, 201301) &amp; London"),
     ("Phone / Email","+91 888 2 898 898  &middot;  info@bluerosepublishers.com"),
     ("Royalty","100% to author; EMI options")],
    "Publish/print/sell ebooks &amp; books worldwide free on the base plan; paid packages with discounts "
    "&amp; easy installments starting from about &#8377;1,990.",
    "Editing, cover design, ISBN, printing, worldwide online distribution, royalty calculator, "
    "marketing add-ons.",
    "First-timers wanting a low entry price, EMI flexibility and a large established author base.",
    "Read reviews and get the deliverables in writing; upsells on marketing are common across assisted publishers."
))

story.append(profile(
    7, "Zorba Books", "PAN-INDIA &middot; 60&ndash;70%",
    [("Contact","Call/WhatsApp +91 88005 09579  &middot;  info@zorbabooks.com"),
     ("Base","Gurugram (serves all India)"),
     ("Trust","2,000+ authors")],
    "Fully customisable, &lsquo;100% transparent&rsquo; packages tailored to budget (quote on request). "
    "Authors retain 60&ndash;70% royalty.",
    "Editing, design, ISBN, print + ebook, real-time royalty dashboard, distribution, 2&ndash;4 month "
    "time-to-market.",
    "Authors who want a transparent, customised quote and a strong royalty share rather than fixed tiers.",
    "Because packages are custom, get an itemised quote so you can compare like-for-like with Clever Fox/Notion Press."
))

story.append(profile(
    8, "BFC Publications", "BLR &middot; TIERED",
    [("Base","Bengaluru (bfcpublications.com)"),
     ("Contact","Enquiry/contact form on website &middot; via Justdial listings"),
     ("Plans","Entry / Regular / Premium")],
    "Tiered packages: Entry (format, edit, ISBN, online listing), Regular (adds cover design + basic "
    "marketing), Premium (full review + social/email/SMS/WhatsApp marketing). Pricing on enquiry.",
    "Editing &amp; formatting, ISBN allocation, cover design, online listing, promotion &amp; distribution.",
    "Bangalore authors who want a structured good/better/best menu with marketing built into the top tier.",
    "Exact prices aren&rsquo;t published &mdash; request a written quote and confirm distribution channels &amp; ISBN ownership."
))

story.append(profile(
    9, "Punya Publishing Pvt. Ltd.", "BLR &middot; AFFORDABLE",
    [("Address","#191, 16th Main, 24th Cross, Banashankari 2nd Stage, Bengaluru 560070"),
     ("Phone","+91 80 2671 0803 / 04  &middot;  Mob +91 98451 84955"),
     ("Email","info@punyapublishing.com")],
    "Positions itself on &lsquo;high-quality books at reasonable, affordable prices&rsquo;; quote-based.",
    "Educational, trade, fiction &amp; poetry publishing; editing, design, printing; author solutions arm.",
    "Authors wanting an established, contactable Bangalore house with a real office and phone support.",
    "Get the scope and royalty terms in writing; confirm whether it&rsquo;s assisted self-publishing or trade terms."
))

story.append(profile(
    10, "Pustak Mahal (Bengaluru branch)", "BLR BRANCH &middot; MASS-MARKET",
    [("Branch","Mission Road, Bengaluru (HQ Delhi)"),
     ("Focus","Low-cost paperbacks &amp; Kindle"),
     ("Categories","GK, self-help, cookery, mythology, languages, children")],
    "Low-cost paperback model with print-on-demand and mass-market pricing; terms on enquiry.",
    "Affordable POD, mass-market retail exposure, digital Kindle distribution.",
    "Authors of mainstream/popular non-fiction (self-help, GK, cookery, children) wanting wide, cheap paperbacks.",
    "More of a traditional/trade list than a per-author package shop &mdash; confirm whether they take your genre."
))

story.append(PageBreak())

# ============ RECOMMENDATIONS ============
story.append(Paragraph("4. Recommended Plan &mdash; Minimum Investment, Maximum Reach", h1))
story.append(Paragraph(
    "Based on the comparison, here is a concrete, low-cost path that maximises reach. The idea: "
    "spend money only where it visibly improves the book (editing + cover), and use free channels "
    "for distribution.", body))

steps = [
    ("Step 1 &mdash; Lock global reach for &#8377;0",
     "Publish the ebook + paperback yourself on <b>Amazon KDP</b>. This alone gives you worldwide "
     "Kindle + print-on-demand with no upfront fee and the highest royalty per copy you can get."),
    ("Step 2 &mdash; Add India POD",
     "List the same book on <b>Pothi.com</b> (Bengaluru) for Flipkart/Amazon.in reach and good "
     "per-copy economics &mdash; still effectively free."),
    ("Step 3 &mdash; Spend only on quality",
     "Pay <i>a la carte</i> for two things that make or break a debut: a <b>professional editor</b> and a "
     "<b>strong cover</b>. Clever Fox&rsquo;s &#8377;2,500&ndash;&#8377;3,000 entry tier or Notion Press&rsquo;s "
     "lower packages can cover this if you don&rsquo;t want to hire freelancers separately."),
    ("Step 4 &mdash; If you want it all done for you",
     "Pick <b>one</b> assisted package and compare net rupees-per-copy, not just headline %: "
     "<b>Notion Press</b> (brand + reach), <b>Clever Fox</b> (100% royalty, clear tiers, Bangalore), or "
     "<b>Zorba/BlueRose</b> (transparent/low-entry). Get the deliverables and ISBN ownership in writing."),
    ("Step 5 &mdash; Build an audience",
     "Optionally serialise on <b>Pratilipi</b> (free, esp. for regional languages) to grow readers who "
     "then buy your print book."),
]
for hh, tt in steps:
    story.append(Paragraph(hh, h2))
    story.append(Paragraph(tt, body))

story.append(Spacer(1, 6))
budget = [
    [Paragraph("Budget level", cell_w), Paragraph("Recommended route", cell_w), Paragraph("Indicative spend", cell_w)],
    [Paragraph("Shoestring", cell_b), Paragraph("KDP + Pothi, self-edit, Canva/freelance cover", cell), Paragraph("&#8377;0 &ndash; &#8377;5,000", cell)],
    [Paragraph("Balanced (recommended)", cell_b), Paragraph("KDP + Pothi for distribution &amp; one entry package (Clever Fox/Notion Press) for editing + cover", cell), Paragraph("&#8377;3,000 &ndash; &#8377;12,000", cell)],
    [Paragraph("Done-for-you", cell_b), Paragraph("One mid package (Notion Press / Clever Fox Papyrus / Zorba custom)", cell), Paragraph("&#8377;12,000 &ndash; &#8377;30,000", cell)],
]
bt = Table(budget, colWidths=[35*mm, 90*mm, 30*mm])
bt.setStyle(TableStyle([
    ("BACKGROUND",(0,0),(-1,0),HEADBG),
    ("ROWBACKGROUNDS",(0,1),(-1,-1),[colors.white, ZEBRA]),
    ("GRID",(0,0),(-1,-1),0.4,LINE),
    ("VALIGN",(0,0),(-1,-1),"MIDDLE"),
    ("TOPPADDING",(0,0),(-1,-1),6),("BOTTOMPADDING",(0,0),(-1,-1),6),
    ("LEFTPADDING",(0,0),(-1,-1),7),("RIGHTPADDING",(0,0),(-1,-1),7),
    ("BACKGROUND",(0,2),(-1,2),colors.HexColor('#eaf5f1')),
]))
story.append(bt)
story.append(PageBreak())

# ============ RED FLAGS ============
story.append(Paragraph("5. How to Avoid Vanity-Press Traps (Red Flags)", h1))
story.append(Paragraph(
    "&lsquo;Genuine and reasonable&rsquo; matters because the assisted-publishing space has some "
    "over-priced operators. Use this checklist before paying anyone:", body))
flags = [
    "<b>You keep the ISBN and the rights.</b> The ISBN should be in your name or yours to control. Walk away if they keep exclusive rights forever.",
    "<b>Transparent, itemised quote.</b> Insist on a written breakdown: editing, cover, ISBN, printing cost/copy, distribution, marketing. Avoid vague &lsquo;all-inclusive&rsquo; lump sums.",
    "<b>Royalty in rupees, not just %.</b> &lsquo;100% royalty&rsquo; after inflated printing costs can pay less than 30% elsewhere. Ask: <i>for a &#8377;250 book, how much do I receive per copy?</i>",
    "<b>No forced bulk purchase.</b> You should never be required to buy hundreds of your own copies.",
    "<b>Real distribution.</b> Confirm the book will actually be live on Amazon &amp; Flipkart, not just their own website.",
    "<b>Check independent reviews</b> (Trustpilot, Google, author forums) and ask to see recent titles they&rsquo;ve published.",
    "<b>Marketing promises.</b> Be skeptical of expensive &lsquo;bestseller&rsquo; or &lsquo;guaranteed sales&rsquo; marketing add-ons &mdash; results are rarely guaranteed.",
]
story.append(ListFlowable(
    [ListItem(Paragraph(f, body_l), leftIndent=4, value="•") for f in flags],
    bulletType="bullet", bulletColor=ACCENT, leftIndent=10))

story.append(Spacer(1, 10))
story.append(HRFlowable(width="100%", thickness=0.6, color=LINE))
story.append(Spacer(1, 6))
story.append(Paragraph(
    "<b>Disclaimer:</b> This report was compiled from publicly available information (company websites, "
    "listings and review sites) in June 2026 for general guidance. Prices, packages, royalty rates and "
    "contact details change frequently &mdash; always confirm directly with the publisher and read the "
    "contract before making any payment. This is not legal or financial advice.", small))

# ============ SOURCES ============
story.append(PageBreak())
story.append(Paragraph("6. Sources", h1))
src = [
    "Write Right &mdash; Top 10 Book Publishing Companies in Bangalore: write-right.in/book-publishing-houses-in-bangalore",
    "eStorytellers &mdash; Top 7 Book Publishers in Bangalore: estorytellers.com/blog/book-publishing-houses-in-bangalore/",
    "Clever Fox Publishing &mdash; Packages / Bangalore / Contact: cleverfoxpublishing.com/our-packages/ ; /self-publishing-in-bangalore/ ; /contact-us/",
    "Notion Press &mdash; FAQ, Packages &amp; Contact: notionpress.com/faq ; /publishing-packages ; /contact",
    "BlueRose Publishers &mdash; Packages / Contact: bluerosepublishers.com/packages/ ; /contact-us/",
    "Zorba Books &mdash; Publishing cost &amp; services: zorbabooks.com/publishing-cost/ ; /book-publishing-services-india-2025-your-complete-guide/",
    "Pothi.com &mdash; Self-publishing, FAQ &amp; contact: pothi.com ; publish.pothi.com/contact/",
    "Pustak Mahal / Pratilipi references via Write Right &amp; eStorytellers (above)",
    "BFC Publications &mdash; Best Book Publisher in Bangalore / packages: bfcpublications.com/best-book-publisher-in-bangalore ; /process",
    "Punya Publishing &mdash; Contact &amp; services: punyapublishing.com/contact ; /book-publishing-services",
    "Amazon KDP royalty references: kindlepreneur.com/kdp-royalty-calculator/ ; iwrity.com/amazon-kdp-royalties-guide",
    "Zorba Books &mdash; Top 10 Self-Publishing Companies in India 2026: zorbabooks.com/top-10-self-publishing-companies-in-india-2026-an-author-focused-guide/",
    "AuthorsWiki &mdash; Top Free Self-Publishing Platforms in India 2026: authorswiki.com/author-guide/top-free-self-publishing-platforms-in-india/",
]
story.append(ListFlowable(
    [ListItem(Paragraph(s, small), value="•", leftIndent=4) for s in src],
    bulletType="bullet", bulletColor=SLATE, leftIndent=10))

# ---- page chrome ----
def chrome(canvas, doc):
    canvas.saveState()
    w, h = A4
    # top rule
    canvas.setStrokeColor(LINE); canvas.setLineWidth(0.5)
    canvas.line(18*mm, h-14*mm, w-18*mm, h-14*mm)
    canvas.setFont("Helvetica", 7.5); canvas.setFillColor(SLATE)
    canvas.drawString(18*mm, h-12.5*mm, "Top Book Publishers in Bangalore — First-Time Author's Guide")
    canvas.drawRightString(w-18*mm, h-12.5*mm, "June 2026")
    # footer
    canvas.line(18*mm, 14*mm, w-18*mm, 14*mm)
    canvas.drawString(18*mm, 10.5*mm, "Compiled from public sources — verify prices before paying.")
    canvas.drawRightString(w-18*mm, 10.5*mm, f"Page {doc.page}")
    canvas.restoreState()

def cover_chrome(canvas, doc):
    canvas.saveState()
    w, h = A4
    canvas.setFillColor(ACCENT)
    canvas.rect(0, h-10*mm, w, 10*mm, fill=1, stroke=0)
    canvas.setFillColor(GOLD)
    canvas.rect(0, h-12*mm, w, 2*mm, fill=1, stroke=0)
    canvas.setFont("Helvetica", 7.5); canvas.setFillColor(SLATE)
    canvas.drawCentredString(w/2, 10*mm, "Research report — for general guidance only.")
    canvas.restoreState()

doc = SimpleDocTemplate(OUT, pagesize=A4,
                        leftMargin=18*mm, rightMargin=18*mm,
                        topMargin=20*mm, bottomMargin=18*mm,
                        title="Top Book Publishers in Bangalore for First-Time Authors",
                        author="Research Report")

# first page uses cover chrome, rest use header/footer
from reportlab.platypus.doctemplate import PageTemplate, BaseDocTemplate
from reportlab.platypus.frames import Frame

frame = Frame(18*mm, 18*mm, A4[0]-36*mm, A4[1]-38*mm, id='main')
doc.addPageTemplates([
    PageTemplate(id='cover', frames=[Frame(18*mm, 16*mm, A4[0]-36*mm, A4[1]-30*mm)], onPage=cover_chrome),
    PageTemplate(id='content', frames=[frame], onPage=chrome),
])

# force content template after first page
from reportlab.platypus import NextPageTemplate
story_final = [NextPageTemplate('content')] + story
# Insert a page break trick: cover is first page automatically using 'cover'
doc.build(story_final)
print("WROTE", OUT)
