
import { sprintf, __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';

const settings = getSetting( 'dummy_data', {} );

const defaultLabel = __(
	'StoreKeeper Payment',
	'woo-gutenberg-products-block'
);

const label = decodeEntities( settings.title ) || defaultLabel;
/**
 * Content component
 */
const Content = () => {
	return decodeEntities( settings.description || '' );
};
/**
 * Label component
 *
 * @param {*} props Props from payment API.
 */
const Label = ( props ) => {
	const { PaymentMethodLabel, text } = props.components;
	return <PaymentMethodLabel text={ text || defaultLabel } />;
};


const ids = [12481,15759,15760]; // todo dynamic

ids.forEach(
	(id) => {
		const Dummy = {
			name: "sk_pay_id_"+id,
			label: <Label text={'StoreKeeper Payment' + id}/>, // todo label
			content: <Content />,
			edit: <Content />,
			canMakePayment: () => true,
			ariaLabel: label,
			supports: {
				features: settings.supports,
			},
		};

		registerPaymentMethod( Dummy );
	}
)
