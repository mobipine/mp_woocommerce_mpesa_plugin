(() => {
  'use strict';
  const { createElement: h } = window.wp.element;
  const { decodeEntities } = window.wp.htmlEntities;
  const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
  const { getSetting } = window.wc.wcSettings;
  const settings = getSetting('mpesa_data', {});
  const title = decodeEntities(settings.title || 'M-PESA');

  const Content = () => h('div', { className: 'src-mpesa-instructions' },
    h('strong', null, 'Pay securely with M-PESA'),
    h('p', null, decodeEntities(settings.description || 'A payment prompt will be sent to the phone number above after you place the order.')),
    h('ol', null,
      h('li', null, 'Confirm the M-PESA phone number in Contact information.'),
      h('li', null, 'Select Place order to receive a prompt on that phone.'),
      h('li', null, 'Enter your M-PESA PIN and wait for payment confirmation.')
    ),
    h('small', null, 'Keep your phone on and unlocked. Never share your PIN with anyone.')
  );

  const Label = () => h('span', { className: 'src-mpesa-label' },
    h('span', null, title),
    settings.icon ? h('img', { src: settings.icon, alt: '' }) : null
  );

  registerPaymentMethod({
    name: 'mpesa',
    label: h(Label),
    content: h(Content),
    edit: h(Content),
    canMakePayment: () => true,
    ariaLabel: title,
    supports: { features: settings.supports },
  });
})();
