import { __ } from '@wordpress/i18n';
import { describeCondition, conditionAction } from './describe';

const T = 'corelabs-product-options';

function Control( { field } ) {
	const ph = field.placeholder || '';
	switch ( field.type ) {
		case 'textarea':
			return <div className="clpo-input clpo-input--area">{ ph || '…' }</div>;
		case 'number':
			return <div className="clpo-input">{ ph || '0' }</div>;
		case 'checkbox':
			return <div className="clpo-input clpo-input--check">{ field.default === 'yes' ? '✓' : '' }</div>;
		case 'radio':
			return (
				<div className="clpo-opts">
					{ ( field.options || [] ).slice( 0, 4 ).map( ( o ) => (
						<span key={ o }><span className="clpo-radio"></span>{ o }</span>
					) ) }
				</div>
			);
		case 'select':
			return <div className="clpo-input">{ ( field.options && field.options[ 0 ] ) || __( 'Choose…', T ) } ▾</div>;
		case 'email':
			return <div className="clpo-input">{ ph || 'name@example.com' }</div>;
		case 'phone':
			return <div className="clpo-input">{ ph || '+1 555 000 0000' }</div>;
		case 'url':
			return <div className="clpo-input">{ ph || 'https://…' }</div>;
		case 'date':
			return <div className="clpo-input">{ __( 'Select date', T ) } 📅</div>;
		case 'time':
			return <div className="clpo-input">{ __( 'Select time', T ) } 🕐</div>;
		case 'file':
			return <div className="clpo-input">📎 { __( 'Choose file…', T ) }</div>;
		case 'swatch':
			return (
				<div className="clpo-opts">
					{ ( field.options || [] ).slice( 0, 6 ).map( ( o ) => (
						<span key={ o.label } className="clpo-swdot" style={ { background: o.color || '#ccc' } } title={ o.label }></span>
					) ) }
				</div>
			);
		case 'price':
			return <div className="clpo-input clpo-input--fee">＋ { field.price || 0 }</div>;
		case 'quantity':
			return <div className="clpo-input" style={ { maxWidth: 120 } }>− { field.default || 0 } ＋</div>;
		case 'buttons':
			return (
				<div className="clpo-opts">
					{ ( field.options || [] ).slice( 0, 4 ).map( ( o ) => (
						<span key={ o } className="clpo-pillopt">{ o }</span>
					) ) }
				</div>
			);
		case 'image_swatch':
			return (
				<div className="clpo-opts">
					{ ( field.options || [] ).slice( 0, 5 ).map( ( o ) => (
						o.image
							? <img key={ o.label } className="clpo-swimg" src={ o.image } alt={ o.label } title={ o.label } />
							: <span key={ o.label } className="clpo-swimg clpo-swimg--empty" title={ o.label }>🖼</span>
					) ) }
				</div>
			);
		default:
			return <div className="clpo-input">{ ph || '…' }</div>;
	}
}

/**
 * One field, rendered checkout-style inside the preview canvas.
 */
export default function FieldPreview( { field, fields, selected, ghost, reason, onSelect, onDuplicate, onRemove, dragHandlers = {} } ) {
	const cls = [
		'clpo-fld',
		`clpo-w-${ field.width === 'half' ? 'half' : 'full' }`,
		selected ? 'is-selected' : '',
		ghost ? 'is-ghost' : '',
		dragHandlers.isDragging ? 'is-dragging' : '',
		dragHandlers.isDropTarget ? 'is-drop-target' : '',
	].filter( Boolean ).join( ' ' );

	const summary = describeCondition( field, [], fields );

	if ( field.type === 'heading' ) {
		return (
			<div
				className={ cls }
				role="button"
				tabIndex={ 0 }
				onClick={ onSelect }
				onKeyDown={ ( e ) => { if ( e.key === 'Enter' ) { onSelect(); } } }
				{ ...dragHandlers.props }
			>
				{ selected && <Ops onDuplicate={ onDuplicate } onRemove={ onRemove } /> }
				{ selected && <span className="clpo-grip" title={ __( 'Drag to reorder', T ) }>⠿</span> }
				<div className="clpo-heading-preview">{ field.label || __( 'Heading', T ) }</div>
				{ field.description ? <div className="clpo-fdesc">{ field.description }</div> : null }
				{ summary && ! ghost ? <span className="clpo-logic-flag">IF · { summary } → { conditionAction( field ) }</span> : null }
			</div>
		);
	}

	return (
		<div
			className={ cls }
			role="button"
			tabIndex={ 0 }
			onClick={ onSelect }
			onKeyDown={ ( e ) => { if ( e.key === 'Enter' ) { onSelect(); } } }
			{ ...dragHandlers.props }
		>
			{ selected && <Ops onDuplicate={ onDuplicate } onRemove={ onRemove } /> }
			{ selected && <span className="clpo-grip" title={ __( 'Drag to reorder', T ) }>⠿</span> }
			<div className="clpo-flabel">
				{ field.label || field.type }
				{ field.required ? <span className="clpo-req">*</span> : null }
				{ parseFloat( field.price ) > 0 ? <span className="clpo-fee-badge">+{ field.price }</span> : null }
			</div>
			{ field.description ? <div className="clpo-fdesc">{ field.description }</div> : null }
			<Control field={ field } />
			{ ghost && reason ? <div className="clpo-fdesc" style={ { marginTop: 7 } }>{ reason }</div> : null }
			{ summary && ! ghost ? <span className="clpo-logic-flag">IF · { summary } → { conditionAction( field ) }</span> : null }
		</div>
	);
}

function Ops( { onDuplicate, onRemove } ) {
	return (
		<span className="clpo-ops">
			<button className="clpo-op" aria-label={ __( 'Duplicate', T ) } onClick={ ( e ) => { e.stopPropagation(); onDuplicate(); } }>⧉</button>
			<button className="clpo-op" aria-label={ __( 'Delete', T ) } onClick={ ( e ) => { e.stopPropagation(); onRemove(); } }>✕</button>
		</span>
	);
}
