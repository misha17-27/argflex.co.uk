<?php
/**
 * Which pieces of copy each page exposes to the admin.
 *
 * Field types: text (one line), area (a few lines), html (rich block),
 * lines (one item per line).
 */
declare(strict_types=1);

function page_schema(): array
{
    return [
        '/' => [
            'label'  => 'Homepage',
            'groups' => [
                'Hero' => [
                    ['hero_eyebrow', 'Eyebrow', 'text'],
                    ['hero_title',   'Heading', 'area', 'HTML allowed — <em>text</em> is shown in orange.'],
                    ['hero_text',    'Intro paragraph', 'area'],
                    ['hero_btn1',    'Primary button', 'text'],
                    ['hero_btn2',    'Secondary button', 'text'],
                    ['hero_tags',    'Tags', 'lines', 'One per line.'],
                ],
                'Trust strip' => [
                    ['trust1_title', 'Item 1 title', 'text'], ['trust1_text', 'Item 1 text', 'area'],
                    ['trust2_title', 'Item 2 title', 'text'], ['trust2_text', 'Item 2 text', 'area'],
                    ['trust3_title', 'Item 3 title', 'text'], ['trust3_text', 'Item 3 text', 'area'],
                    ['trust4_title', 'Item 4 title', 'text'], ['trust4_text', 'Item 4 text', 'area'],
                ],
                'Ticker' => [
                    ['ticker', 'Scrolling words', 'lines', 'One per line. Repeated twice so the loop is seamless.'],
                ],
                'Sections' => [
                    ['cats_eyebrow',  'Categories — eyebrow', 'text'],
                    ['cats_title',    'Categories — heading', 'text'],
                    ['cats_text',     'Categories — text', 'area'],
                    ['feat_eyebrow',  'Featured — eyebrow', 'text'],
                    ['feat_title',    'Featured — heading', 'text'],
                    ['feat_text',     'Featured — text', 'area'],
                    ['inds_eyebrow',  'Industries — eyebrow', 'text'],
                    ['inds_title',    'Industries — heading', 'text'],
                    ['inds_text',     'Industries — text', 'area'],
                    ['blog_eyebrow',  'Blog — eyebrow', 'text'],
                    ['blog_title',    'Blog — heading', 'text'],
                    ['blog_text',     'Blog — text', 'area'],
                ],
                'About block' => [
                    ['about_eyebrow', 'Eyebrow', 'text'],
                    ['about_title',   'Heading', 'text'],
                    ['about_p1',      'First paragraph', 'area'],
                    ['about_p2',      'Second paragraph', 'area'],
                    ['about_checks',  'Ticked points', 'lines', 'One per line.'],
                    ['about_btn',     'Button', 'text'],
                ],
                'Numbers' => [
                    ['stat1_value', 'Number 1', 'text'], ['stat1_label', 'Label 1', 'text'],
                    ['stat2_value', 'Number 2', 'text'], ['stat2_label', 'Label 2', 'text'],
                    ['stat3_value', 'Number 3', 'text'], ['stat3_label', 'Label 3', 'text'],
                    ['stat4_label', 'Label 4', 'text', 'The number is the live product count.'],
                ],
                'Closing call to action' => [
                    ['cta_title', 'Heading', 'text'],
                    ['cta_text',  'Text', 'area'],
                    ['cta_btn',   'Button', 'text'],
                ],
            ],
        ],

        '/shop/' => ['label' => 'Shop', 'groups' => ['Heading' => [
            ['eyebrow', 'Eyebrow', 'text'],
            ['title',   'Heading', 'text'],
            ['intro',   'Intro (shown after the product count)', 'area'],
            ['help_title', 'Sidebar help — heading', 'text'],
            ['help_text',  'Sidebar help — text', 'area'],
            ['help_btn',   'Sidebar help — button', 'text'],
        ]]],

        '/about-us/' => ['label' => 'About us', 'groups' => [
            'Heading' => [
                ['eyebrow', 'Eyebrow', 'text'],
                ['title',   'Heading', 'text'],
                ['intro',   'Intro', 'area'],
            ],
            'Main block' => [
                ['split_eyebrow', 'Eyebrow', 'text'],
                ['split_title',   'Heading', 'text'],
                ['split_p1',      'First paragraph', 'area'],
                ['split_p2',      'Second paragraph', 'area'],
                ['split_checks',  'Ticked points', 'lines', 'One per line.'],
                ['split_btn',     'Button', 'text'],
            ],
            'How we work' => [
                ['steps_eyebrow', 'Eyebrow', 'text'],
                ['steps_title',   'Heading', 'text'],
                ['steps_text',    'Text', 'area'],
                ['step1_title', 'Step 1 title', 'text'], ['step1_text', 'Step 1 text', 'area'],
                ['step2_title', 'Step 2 title', 'text'], ['step2_text', 'Step 2 text', 'area'],
                ['step3_title', 'Step 3 title', 'text'], ['step3_text', 'Step 3 text', 'area'],
                ['step4_title', 'Step 4 title', 'text'], ['step4_text', 'Step 4 text', 'area'],
            ],
            'Closing call to action' => [
                ['cta_title', 'Heading', 'text'],
                ['cta_text',  'Text', 'area'],
                ['cta_btn',   'Button', 'text'],
            ],
        ]],

        '/contacts/' => ['label' => 'Contacts', 'groups' => ['Page' => [
            ['eyebrow',    'Eyebrow', 'text'],
            ['title',      'Heading', 'text'],
            ['intro',      'Intro', 'area'],
            ['form_title', 'Form heading', 'text'],
            ['form_note',  'Note under the form', 'area'],
            ['form_btn',   'Send button', 'text'],
        ]]],

        '/blog/' => ['label' => 'Blog index', 'groups' => ['Heading' => [
            ['eyebrow', 'Eyebrow', 'text'],
            ['title',   'Heading', 'text'],
            ['intro',   'Intro', 'area'],
            ['cta_title', 'Closing heading', 'text'],
            ['cta_text',  'Closing text', 'area'],
            ['cta_btn',   'Closing button', 'text'],
        ]]],

        '/refund_returns/' => ['label' => 'Refunds and returns', 'groups' => [
            'Heading' => [
                ['eyebrow', 'Eyebrow', 'text'],
                ['title',   'Heading', 'text'],
                ['intro',   'Intro', 'area'],
            ],
            'Policy' => [
                ['body', 'Policy text', 'html', 'Full HTML. Headings from &lt;h2&gt; down.'],
            ],
        ]],

        '/cart/' => ['label' => 'Cart', 'groups' => ['Page' => [
            ['eyebrow',     'Eyebrow', 'text'],
            ['title',       'Heading', 'text'],
            ['intro',       'Intro', 'area'],
            ['empty_title', 'Empty cart — heading', 'text'],
            ['empty_text',  'Empty cart — text', 'area'],
            ['empty_btn',   'Empty cart — button', 'text'],
        ]]],

        '/checkout/' => ['label' => 'Checkout', 'groups' => [
            'Form' => [
                ['eyebrow', 'Eyebrow', 'text'],
                ['title',   'Heading', 'text'],
                ['intro',   'Intro', 'area'],
                ['pay_title', 'Payment block — heading', 'text'],
                ['pay_text',  'Payment block — text', 'area'],
                ['pay_note',  'Payment block — note', 'area'],
            ],
            'After the order' => [
                ['done_eyebrow', 'Eyebrow', 'text'],
                ['done_title',   'Heading', 'text', 'The reference is added automatically.'],
                ['done_intro',   'Intro', 'area'],
                ['step1_title', 'Step 1 title', 'text'], ['step1_text', 'Step 1 text', 'area'],
                ['step2_title', 'Step 2 title', 'text'], ['step2_text', 'Step 2 text', 'area'],
                ['step3_title', 'Step 3 title', 'text'], ['step3_text', 'Step 3 text', 'area'],
            ],
        ]],

        '/wishlist/' => ['label' => 'Wishlist', 'groups' => ['Page' => [
            ['eyebrow',     'Eyebrow', 'text'],
            ['title',       'Heading', 'text'],
            ['intro',       'Intro', 'area'],
            ['empty_title', 'Empty — heading', 'text'],
            ['empty_text',  'Empty — text', 'area'],
            ['empty_btn',   'Empty — button', 'text'],
        ]]],

        '/compare/' => ['label' => 'Compare', 'groups' => ['Page' => [
            ['eyebrow', 'Eyebrow', 'text'],
            ['title',   'Heading', 'text'],
            ['intro',   'Intro', 'area'],
        ]]],

        '/my-account/' => ['label' => 'My account', 'groups' => ['Page' => [
            ['eyebrow',   'Eyebrow', 'text'],
            ['title',     'Heading', 'text'],
            ['intro',     'Intro', 'area'],
            ['signin_title', 'Sign in — heading', 'text'],
            ['signin_note',  'Sign in — note', 'area'],
            ['apply_title',  'Trade account — heading', 'text'],
            ['apply_text',   'Trade account — text', 'area'],
            ['apply_checks', 'Trade account — points', 'lines', 'One per line.'],
            ['apply_btn',    'Trade account — button', 'text'],
        ]]],

        '/404' => ['label' => 'Page not found (404)', 'groups' => ['Page' => [
            ['eyebrow', 'Eyebrow', 'text'],
            ['title',   'Heading', 'text'],
            ['intro',   'Intro', 'area'],
        ]]],
    ];
}
