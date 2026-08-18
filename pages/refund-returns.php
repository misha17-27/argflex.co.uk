<?php
declare(strict_types=1);

set_page([
    'title'       => 'Refund and Returns Policy — ' . SITE_NAME,
    'description' => 'Our refund and returns policy lasts 30 days from delivery. Items must be unused, in their original packaging and accompanied by proof of purchase.',
    'crumbs'      => [['label' => 'Refunds and returns']],
]);

require ROOT_DIR . '/inc/header.php';
?>
<section class="pg-head">
  <div class="wrap narrow">
    <span class="eyebrow">Policy</span>
    <h1>Refund and Returns Policy</h1>
    <p>Last updated <?= date('j F Y') ?>. If anything here is unclear, call <a href="tel:<?= SITE_PHONE_HREF ?>"><?= SITE_PHONE ?></a> and we will talk it through.</p>
  </div>
</section>

<section style="padding-top:36px">
  <div class="wrap narrow">
    <div class="rich policy">
      <h2>Overview</h2>
      <p>Our refund and returns policy lasts 30 days. If 30 days have passed since your purchase, we can&rsquo;t offer you a full refund or exchange.</p>
      <p>To be eligible for a return, your item must be unused and in the same condition that you received it. It must also be in the original packaging.</p>
      <p>Several types of goods are exempt from being returned. Hazardous materials, flammable liquids and gases cannot be returned. Hose cut to a length you specified is made to order and cannot be returned unless it is faulty.</p>

      <h3>Additional non-returnable items</h3>
      <ul>
        <li>Gift cards</li>
        <li>Cut-to-length hose prepared to your specification</li>
        <li>Products altered or assembled at your request</li>
      </ul>

      <p>To complete your return we require a receipt or proof of purchase. Please do not send your purchase back to the manufacturer.</p>

      <h3>Partial refunds</h3>
      <p>There are certain situations where only partial refunds are granted:</p>
      <ul>
        <li>Any item not in its original condition, damaged or missing parts for reasons not due to our error</li>
        <li>Any item returned more than 30 days after delivery</li>
      </ul>

      <h2>Refunds</h2>
      <p>Once your return is received and inspected we will email you to confirm that we have received the item, and to tell you whether your refund has been approved.</p>
      <p>If approved, the refund is processed and a credit is automatically applied to your original method of payment within a certain number of days.</p>

      <h3>Late or missing refunds</h3>
      <p>If you haven&rsquo;t received a refund yet, check your bank account again, then contact your credit card company &mdash; it can take some time before a refund is officially posted. Next contact your bank, as there is often a processing delay. If you have done all of this and still have not received your refund, contact us at <a href="mailto:<?= SITE_EMAIL ?>"><?= SITE_EMAIL ?></a>.</p>

      <h2>Exchanges</h2>
      <p>We only replace items if they are defective or damaged. If you need to exchange a product for the same item, email us at <a href="mailto:<?= SITE_EMAIL ?>"><?= SITE_EMAIL ?></a> and we will confirm the return address.</p>

      <h2>Shipping returns</h2>
      <p>You are responsible for paying the postage costs of returning your item. Shipping costs are non-refundable. If you receive a refund, the cost of return shipping is deducted from it.</p>
      <p>Depending on where you live, the time it takes for an exchanged product to reach you may vary. For anything of value, consider a trackable shipping service or shipping insurance &mdash; we cannot guarantee that we will receive your returned item.</p>

      <h2>Need help?</h2>
      <p>Contact us at <a href="mailto:<?= SITE_EMAIL ?>"><?= SITE_EMAIL ?></a> or <a href="tel:<?= SITE_PHONE_HREF ?>"><?= SITE_PHONE ?></a> for questions about refunds and returns.</p>
    </div>
  </div>
</section>

<?php require ROOT_DIR . '/inc/footer.php'; ?>
