/**
 * Pure builder state: reducer, action creators, selectors. No @wordpress
 * imports here so it is unit-testable with plain Jest. The @wordpress/data
 * store wrapper lives in store.js.
 */

export const DEFAULTS = {
	text: { label: 'Text field', required: false, price: 0, options: [], condition: null },
	textarea: { label: 'Text area', required: false, price: 0, options: [], condition: null },
	number: { label: 'Number', required: false, price: 0, options: [], condition: null },
	checkbox: { label: 'Checkbox', required: false, price: 0, options: [], condition: null },
	radio: { label: 'Choose one', required: false, price: 0, options: [ 'Option 1' ], condition: null },
	select: { label: 'Select', required: false, price: 0, options: [ 'Option 1' ], condition: null },
	price: { label: 'Surcharge', required: false, price: 0, options: [], condition: null },
	swatch: {
		label: 'Choose a colour',
		required: false,
		price: 0,
		options: [
			{ label: 'Red', color: '#ef4444' },
			{ label: 'Blue', color: '#3b82f6' },
		],
		condition: null,
	},
	date: { label: 'Pick a date', required: false, price: 0, options: [], min: '', max: '', condition: null },
};

let counter = 0;
export function makeId() {
	counter += 1;
	return `fld_${ counter }_${ Math.random().toString( 36 ).slice( 2, 7 ) }`;
}

export const initialState = { fields: [], selectedId: null, tab: 'build' };

export function reducer( state = initialState, action = {} ) {
	switch ( action.type ) {
		case 'ADD_FIELD': {
			const defaults = DEFAULTS[ action.fieldType ] || DEFAULTS.text;
			const field = { id: action.id, type: action.fieldType, ...defaults, options: [ ...defaults.options ] };
			return { ...state, fields: [ ...state.fields, field ], selectedId: field.id };
		}
		case 'UPDATE_FIELD':
			return {
				...state,
				fields: state.fields.map( ( f ) => ( f.id === action.id ? { ...f, ...action.patch } : f ) ),
			};
		case 'REMOVE_FIELD': {
			const fields = state.fields
				.filter( ( f ) => f.id !== action.id )
				.map( ( f ) => {
					let nf = f;
					if ( f.condition && f.condition.field === action.id ) {
						nf = { ...nf, condition: null };
					}
					if ( Array.isArray( f.conditions ) && f.conditions.some( ( r ) => r.field === action.id ) ) {
						nf = { ...nf, conditions: f.conditions.filter( ( r ) => r.field !== action.id ) };
					}
					return nf;
				} );
			return { ...state, fields, selectedId: state.selectedId === action.id ? null : state.selectedId };
		}
		case 'REORDER': {
			const fields = [ ...state.fields ];
			if ( action.to < 0 || action.to >= fields.length ) {
				return state;
			}
			const [ moved ] = fields.splice( action.from, 1 );
			fields.splice( action.to, 0, moved );
			return { ...state, fields };
		}
		case 'SELECT':
			return { ...state, selectedId: action.id };
		case 'SET_TAB':
			return { ...state, tab: action.tab };
		case 'HYDRATE':
			return { ...state, fields: Array.isArray( action.fields ) ? action.fields : [] };
		default:
			return state;
	}
}

export const actions = {
	addField: ( fieldType ) => ( { type: 'ADD_FIELD', fieldType, id: makeId() } ),
	updateField: ( id, patch ) => ( { type: 'UPDATE_FIELD', id, patch } ),
	removeField: ( id ) => ( { type: 'REMOVE_FIELD', id } ),
	reorder: ( from, to ) => ( { type: 'REORDER', from, to } ),
	selectField: ( id ) => ( { type: 'SELECT', id } ),
	setTab: ( tab ) => ( { type: 'SET_TAB', tab } ),
	hydrate: ( fields ) => ( { type: 'HYDRATE', fields } ),
};

export const selectors = {
	getFields: ( state ) => state.fields,
	getSelected: ( state ) => state.fields.find( ( f ) => f.id === state.selectedId ) || null,
	getSelectedId: ( state ) => state.selectedId,
	getTab: ( state ) => state.tab,
};
