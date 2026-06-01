import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { TabPanel, Button, Snackbar } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { STORE } from './store';
import FieldPalette from './FieldPalette';
import Canvas from './Canvas';
import FieldSettings from './FieldSettings';
import LivePreview from './LivePreview';

export default function App( { productId } ) {
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const [ notice, setNotice ] = useState( '' );
	const [ saving, setSaving ] = useState( false );

	const save = async () => {
		setSaving( true );
		try {
			await apiFetch( {
				path: `apo/v1/product/${ productId }/fields`,
				method: 'POST',
				data: { fields },
			} );
			setNotice( __( 'Options saved.', 'conditional-product-options' ) );
		} catch ( e ) {
			setNotice( __( 'Save failed. Please try again.', 'conditional-product-options' ) );
		}
		setSaving( false );
	};

	return (
		<div className="apo-app">
			<TabPanel
				className="apo-tabs"
				tabs={ [
					{ name: 'build', title: __( 'Build', 'conditional-product-options' ) },
					{ name: 'preview', title: __( 'Preview', 'conditional-product-options' ) },
				] }
			>
				{ ( tab ) =>
					tab.name === 'build' ? (
						<div className="apo-build">
							<FieldPalette />
							<Canvas />
							<FieldSettings />
						</div>
					) : (
						<LivePreview />
					)
				}
			</TabPanel>
			<div className="apo-actions">
				<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
					{ __( 'Save options', 'conditional-product-options' ) }
				</Button>
			</div>
			{ notice && <Snackbar onRemove={ () => setNotice( '' ) }>{ notice }</Snackbar> }
		</div>
	);
}
