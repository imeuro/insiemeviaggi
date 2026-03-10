import { getSetting } from '@woocommerce/settings';
import { decodeEntities } from '@wordpress/html-entities';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';

//Vivacom data to global object
const getVivaComData = () => {
    const vivaComData = getSetting( 'vivacom_smart_data', null );
    if (!vivaComData) throw new Error('VivaWallet form data not available');
    return vivaComData;
}

const Content = () => {
    return decodeEntities(getVivaComData()?.description || '');
};

const Label = () => {
    return (
        <div>
        <span>{getVivaComData()?.title ?? "Card Payment"}</span>
        <img
            src={getVivaComData()?.logo_url}
            alt={getVivaComData()?.title}
        />
        </div>
    );
};

registerPaymentMethod({
   name:  "vivacom_smart",
    label: <Label />,
    ariaLabel: getVivaComData()?.title ?? "Card Payment",
    canMakePayment: () => true,
    content: <Content />,
    edit: <Content />,
    supports: {
        features: getVivaComData()?.supports ?? [],
    },
});