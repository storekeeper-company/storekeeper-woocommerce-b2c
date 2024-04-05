/**
 * This script is used to render dynamic storekeeper payment methods
 * and handles the rendering of all blocks
 */
import * as React from 'react';
import { sprintf, __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';

const registerPaymentMethodForId = ( paymentMethodId ) => {
	const settings = getSetting( `${ paymentMethodId }_data`, {} );
	const defaultLabel = sprintf(
		/* translators: StoreKeeper Payment %s. */
		__( 'StoreKeeper Payment %s', 'storekeeper-for-woocommerce' ),
		paymentMethodId
	);

	const label = decodeEntities( settings.title ) || defaultLabel;
	const icon = decodeEntities( settings.icon ) || null;

	const Icon = () => {
		return icon ? (
			<img
				src={ icon }
				style={ { float: 'right', marginRight: '20px' } }
				alt={ label }
			/>
		) : '';
	}

	/**
	 * Content component
	 */
	const Content = () => {
		return decodeEntities( settings.description || '' );
	};

	const Label = () => {
		return <span style={{ width: '100%' }}>
			{ label }
			<Icon/>
		</span>;
	};

	const StoreKeeperPaymentMethod = {
		name: paymentMethodId,
		label: <Label/>,
		content: <Content />,
		edit: <Content />,
		canMakePayment: () => true,
		ariaLabel: label,
		supports: {
			features: settings.supports,
		},
	};

	registerPaymentMethod( StoreKeeperPaymentMethod );
};

const allPaymentMethodData = getSetting('paymentMethodData');
Object.keys(allPaymentMethodData).forEach((paymentMethodId) => {
	if (/^sk_pay_id_\d+$/.test(paymentMethodId)) {
		registerPaymentMethodForId(paymentMethodId);
	}
});
