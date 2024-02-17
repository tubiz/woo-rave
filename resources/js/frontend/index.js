
import { sprintf, __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';

const settings = getSetting( 'tbz_rave_data', {} );

const defaultLabel = __(
	'Flutterwave',
	'woo-rave'
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
const Label2 = ( props ) => {
	const { PaymentMethodLabel } = props.components;
	return <PaymentMethodLabel text={ label } />;
};

const Label = ( props ) => {
	const { PaymentMethodLabel } = props.components;
	return (
		<>
			<div style={{display: 'flex', flexDirection: 'row', gap: '0.5rem'}}>
				<div>
					<PaymentMethodLabel text={ label } />
				</div>
				<img src={ settings.checkout_image_url} alt="Flutterwave payment methods" />
			</div>
		</>
	);
};

/**
 * Rave payment method config object.
 */
const TBZ_Flutterwave_WC = {
	name: "tbz_rave",
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};

registerPaymentMethod( TBZ_Flutterwave_WC );
