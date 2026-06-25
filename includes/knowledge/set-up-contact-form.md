---
type: Skill
title: Put a contact form on a page
description: Before adding a Contact Form 7 contact form to a page or post — to find or create the form and embed its shortcode.
---

Recipe: put a Contact Form 7 contact form on a page.

Goal: render a CF7 contact form on a page or post by embedding its shortcode. CF7 forms are NOT regular content: they live behind the "contact-form-7" tool, not the "content" tool, and a form is placed by dropping its shortcode into page/post content.

STEP 1 - FIND OR CREATE THE FORM (through the "contact-form-7" tool)
- contact-form-7 execute og-cf7/list-forms: lists existing forms, each with its "shortcode" field. If a suitable form already exists, take its shortcode and skip to Step 2.
- If none fits, contact-form-7 execute og-cf7/create-form with a "title" (and optional form/mail settings) to make one. The result includes the new form's "shortcode". IMPORTANT: the mail.recipient you set is where every submission is emailed and CF7 does not validate it; confirm it is correct.

STEP 2 - PLACE THE SHORTCODE (through the "content" tool)
The shortcode looks like [contact-form-7 id="abc1234" title="Contact"]. Put it in the content of a page or post:
- New page: content execute content/create-page with content set to the shortcode (optionally wrapped in a paragraph block, e.g. <!-- wp:paragraph --><p>[contact-form-7 id="abc1234" title="Contact"]</p><!-- /wp:paragraph -->).
- Existing page/post: content execute content/update-page (or content/update-post) to add the shortcode to the body.

STEP 3 - CONFIRM
Surface the page's edit_link (from the content ability) and the form's edit_link (from the contact-form-7 ability) so a human can review both. The shortcode renders the live form on the front end; editing the form later (contact-form-7 execute og-cf7/update-form) updates it everywhere it is embedded.
