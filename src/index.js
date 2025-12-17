import { decodeEntities } from '@wordpress/html-entities';
import { useState } from '@wordpress/element';

const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { getSetting } = window.wc.wcSettings;

const settings = getSetting('mpesa_data', {});

const label = decodeEntities(settings.title);

const Content = () => {
    const [phoneNumber, setPhoneNumber] = useState('');

    const handleInputChange = (event) => {
        setPhoneNumber(event.target.value);
    };

    return (
        <div>
            <p>{decodeEntities(settings.description || '')}</p>
            
        </div>
    );
};

const Icon = () => {
    return settings.icon ? <img src={settings.icon} style={{}} /> : '';
};

const Label = () => {
    return (
        <span style={{ width: '100%', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
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