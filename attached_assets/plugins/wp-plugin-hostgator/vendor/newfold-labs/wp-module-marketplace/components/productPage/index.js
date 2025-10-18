import ProductPageError from './ProductPageError';
import ProductPageLoading from './ProductPageLoading';

const initialState = {
	html: null,
	loading: true,
	error: false,
};

const ProductPage = ( { productPageId, methods, constants } ) => {
	const [ data, setData ] = methods.useState( {
		...initialState,
	} );

	methods.useEffect( () => {
		// Reset the state
		setData( {
			...initialState,
		} );

		methods
			.apiFetch( {
				url: methods.NewfoldRuntime.createApiUrl(
					`/newfold-marketplace/v1/products/page`,
					{ id: productPageId }
				),
			} )
			.then( ( response ) => {
				if ( response.hasOwnProperty( 'html' ) ) {
					// Set the html content
					setData( {
						html: response.html,
						loading: false,
						error: false,
					} );
				} else {
					// Invoke error state
					setData( {
						html: null,
						loading: false,
						error: true,
					} );
				}
			} )
			.catch( () => {
				// Invoke error state
				setData( {
					html: null,
					loading: false,
					error: true,
				} );
			} );
	}, [ productPageId ] );

	return (
		<div>
			{ data.loading && <ProductPageLoading /> }
			{ data.error && (
				<ProductPageError
					text={ constants.text?.productPage?.error ?? {} }
				/>
			) }
			{ data.html && (
				<div
					dangerouslySetInnerHTML={ {
						__html: data.html,
					} }
				/>
			) }
		</div>
	);
};

export default ProductPage;
