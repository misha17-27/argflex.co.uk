<?php
/**
 * The chat bubble, bottom right.
 *
 * Three questions and a handover: what are you after, your name, your number,
 * then WhatsApp with the message already written. Nothing clever — it is a
 * form wearing a conversation, which is the point: a form asking for three
 * things at once gets abandoned, the same three asked one at a time does not.
 *
 * Bottom RIGHT because the cookie banner lives bottom left (.consent), and
 * two panels fighting over one corner is how a visitor loses the close
 * button on a phone.
 *
 * Hidden entirely when there is no WhatsApp number to hand over to — a chat
 * that ends nowhere is worse than no chat.
 */
require_once ROOT_DIR . '/inc/commerce.php';
require_once ROOT_DIR . '/inc/security.php';

if (whatsapp_number() === '') return;

/* What the first question offers. Buying intents rather than the shop's three
   top-level categories, because "Rubber hoses" is how the catalogue is filed
   and "Fuel & oil" is what somebody came here for. */
$chatPicks = [
    'Fuel & oil hose',
    'Welding gas — oxygen, acetylene',
    'Air & pneumatic',
    'Water, garden & ducting',
    'Clamps & couplings',
    'Something else',
];
?>
<div class="chat" id="chat" data-chat
     data-endpoint="/chat-lead/" data-token="<?= e(form_token('chat')) ?>">

  <button type="button" class="chat-open" data-chat-open aria-expanded="false" aria-controls="chat-panel">
    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.9 8.9 0 0 1-4-.9L3 21l1.9-4.6A8.4 8.4 0 0 1 12 3a8.4 8.4 0 0 1 9 8.5z"/>
    </svg>
    <span class="sr">Open the chat</span>
  </button>

  <div class="chat-pn" id="chat-panel" hidden>
    <header class="chat-hd">
      <span class="chat-av" aria-hidden="true">
        <img src="<?= e(img_src('assets/img/site/logo.png')) ?>" alt="" width="40" height="40">
      </span>
      <span class="chat-who">
        <b><?= e(SITE_NAME) ?></b>
        <small>Two questions and we will call you back</small>
      </span>
      <button type="button" class="chat-ic" data-chat-reset title="Start again">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" aria-hidden="true">
          <path d="M3 12a9 9 0 1 0 2.6-6.4M3 4v5h5"/>
        </svg>
        <span class="sr">Start again</span>
      </button>
      <button type="button" class="chat-ic" data-chat-close title="Close">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" aria-hidden="true">
          <path d="M6 6l12 12M18 6L6 18"/>
        </svg>
        <span class="sr">Close the chat</span>
      </button>
    </header>

    <div class="chat-log" data-chat-log role="log" aria-live="polite"></div>

    <form class="chat-in" data-chat-form>
      <label for="chat-say" class="sr">Your message</label>
      <input id="chat-say" type="text" data-chat-say autocomplete="off"
             placeholder="Write a message…" maxlength="120">
      <div class="hp" aria-hidden="true">
        <label for="chat-website">Leave this field empty</label>
        <input id="chat-website" type="text" data-chat-trap tabindex="-1" autocomplete="off">
      </div>
      <button type="submit" class="chat-send" title="Send">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M5 12h13M12 5l7 7-7 7"/>
        </svg>
        <span class="sr">Send</span>
      </button>
    </form>
  </div>

  <script type="application/json" data-chat-picks><?= json_encode($chatPicks, JSON_UNESCAPED_UNICODE) ?></script>
</div>
