import { decodeEntities } from '@wordpress/html-entities';

const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { getSetting } = window.wc.wcSettings;

const settings = getSetting('mpesa_data', {});

const label = decodeEntities(settings.title);

const Content = () => {
    return (
        <div className="src-mpesa-instructions">
            <strong>Pay securely with M-PESA</strong>
            <p>{decodeEntities(settings.description || 'A payment prompt will be sent to the phone number above after you place the order.')}</p>
            <ol>
                <li>Confirm the M-PESA phone number in Contact information.</li>
                <li>Select Place order to receive a prompt on that phone.</li>
                <li>Enter your M-PESA PIN and wait for payment confirmation.</li>
            </ol>
            <small>Keep your phone on and unlocked. Never share your PIN with anyone.</small>
        </div>
    );
};

const Icon = () => {
    return settings.icon ? <img src={settings.icon} alt="" /> : '';
};

const Label = () => {
    return (
        <span className="src-mpesa-label">
            {label}
            <Icon />
        </span>
    );
};


registerPaymentMethod({
    name: 'mpesa',
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
});
