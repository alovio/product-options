import { createReduxStore, register } from '@wordpress/data';
import { reducer, actions, selectors } from './reducer';

export const STORE = 'apo/builder';

const store = createReduxStore( STORE, { reducer, actions, selectors } );
register( store );

export default store;
