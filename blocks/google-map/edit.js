/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	Button,
} from '@wordpress/components';
import { useState, useEffect, useRef, useCallback } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './editor.scss';

/**
 * Edit component for the Google Map block.
 *
 * @param {Object} props               Block props.
 * @param {Object} props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set block attributes.
 * @return {Element} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
	const { address, lat, lng } = attributes;
	const blockProps = useBlockProps({
		className: 'google-map-block-editor'
	});
	
	const mapRef = useRef(null);
	const [map, setMap] = useState(null);
	const [marker, setMarker] = useState(null);
	const [isApiLoaded, setIsApiLoaded] = useState(false);
	const isUpdatingProgrammatically = useRef(false);

	// Stabilize the marker drag handler with useCallback
	const handleMarkerDrag = useCallback(() => {
		if (!marker || isUpdatingProgrammatically.current) return;
		
		const position = marker.getPosition();
		const newLat = position.lat().toString();
		const newLng = position.lng().toString();
		
		setAttributes({
			lat: newLat,
			lng: newLng
		});

		// Reverse geocode to get address
		const geocoder = new window.google.maps.Geocoder();
		geocoder.geocode({ location: position }, (results, status) => {
			if (status === 'OK' && results[0]) {
				setAttributes({
					address: results[0].formatted_address,
					lat: newLat,
					lng: newLng
				});
			}
		});
	}, [marker, setAttributes]);

	// Load Google Maps API
	useEffect(() => {
		if (window.google && window.google.maps) {
			setIsApiLoaded(true);
			return;
		}

		// Check if script is already being loaded
		const existingScript = document.querySelector('script[src*="maps.googleapis.com/maps/api/js"]');
		if (existingScript) {
			// Wait for existing script to load
			const checkGoogleMaps = () => {
				if (window.google && window.google.maps) {
					setIsApiLoaded(true);
				} else {
					setTimeout(checkGoogleMaps, 100);
				}
			};
			checkGoogleMaps();
			return;
		}

		const script = document.createElement('script');
		script.src = 'https://maps.googleapis.com/maps/api/js?key=AIzaSyCWIUdebNRovvJryUDibH8cwjkRsPI2M_8&sensor=false';
		script.async = true;
		script.defer = true;
		script.onload = () => setIsApiLoaded(true);
		script.onerror = () => {
			console.error('Failed to load Google Maps API');
		};
		document.head.appendChild(script);

		return () => {
			// Don't remove script on cleanup as it might be used by other components
		};
	}, []);

	// Initialize map
	useEffect(() => {
		if (!isApiLoaded || !mapRef.current || map) return;

		const defaultCenter = lat && lng 
			? { lat: parseFloat(lat), lng: parseFloat(lng) }
			: { lat: 51.5074, lng: -0.1278 }; // London default

		const newMap = new window.google.maps.Map(mapRef.current, {
			zoom: 10,
			center: defaultCenter,
			scrollwheel: false,
			draggable: false, // Disable map dragging
			mapTypeId: window.google.maps.MapTypeId.TERRAIN,
			disableDefaultUI: true,
			zoomControl: true,
			zoomControlOptions: {
				style: window.google.maps.ZoomControlStyle.SMALL,
				position: window.google.maps.ControlPosition.LEFT_BOTTOM
			}
		});

		const newMarker = new window.google.maps.Marker({
			position: defaultCenter,
			map: newMap,
			draggable: true,
			title: "Drag to adjust location"
		});

		// Map panning is disabled, so no drag event handling needed

		setMap(newMap);
		setMarker(newMarker);
	}, [isApiLoaded]);

	// Add marker drag listener when marker is ready
	useEffect(() => {
		if (!marker) return;

		const dragListener = marker.addListener('dragend', handleMarkerDrag);
		
		return () => {
			window.google.maps.event.removeListener(dragListener);
		};
	}, [marker, handleMarkerDrag]);

	// Update marker position when attributes change (but don't reinitialize map)
	useEffect(() => {
		if (!map || !marker || !lat || !lng) return;
		
		// Only update if this is a programmatic change (e.g., from address search)
		isUpdatingProgrammatically.current = true;
		
		const newPosition = { lat: parseFloat(lat), lng: parseFloat(lng) };
		marker.setPosition(newPosition);
		map.setCenter(newPosition);
		
		// Reset flag after a brief delay
		setTimeout(() => {
			isUpdatingProgrammatically.current = false;
		}, 100);
	}, [lat, lng, map, marker]);

	// Handle address search with useCallback for stability
	const handleAddressSearch = useCallback(() => {
		if (!address || !window.google) return;

		const geocoder = new window.google.maps.Geocoder();
		geocoder.geocode({ address }, (results, status) => {
			if (status === 'OK' && results[0]) {
				const location = results[0].geometry.location;
				const newLat = location.lat().toString();
				const newLng = location.lng().toString();

				setAttributes({
					address: results[0].formatted_address,
					lat: newLat,
					lng: newLng
				});

				if (map && marker) {
					map.setCenter(location);
					marker.setPosition(location);
				}
			}
		});
	}, [address, map, marker, setAttributes]);

	const handleKeyDown = (event) => {
		if (event.key === 'Enter') {
			handleAddressSearch();
		}
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={__("Map Settings", "kate-toms-core")}>
					<TextControl
						label={__("Address", "kate-toms-core")}
						value={address}
						onChange={value => setAttributes({ address: value })}
						onKeyDown={handleKeyDown}
						help={__(
							"Enter an address and press Enter to search",
							"kate-toms-core"
						)}
					/>
					<Button
						variant="primary"
						onClick={handleAddressSearch}
						disabled={!address}>
						{__("Search Address", "kate-toms-core")}
					</Button>
					{lat && lng && (
						<div
							style={{
								marginTop: "10px",
								fontSize: "12px",
								color: "#666",
							}}>
							<strong>
								{__("Coordinates:", "kate-toms-core")}
							</strong>
							<br />
							{__("Lat:", "kate-toms-core")} {lat}
							<br />
							{__("Lng:", "kate-toms-core")} {lng}
						</div>
					)}
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div className='google-map-editor'>
					<div className='google-map-editor__map-container'>
						{!isApiLoaded ? (
							<div className='google-map-editor__loading'>
								{__("Loading Google Maps...", "kate-toms-core")}
							</div>
						) : (
							<div
								key='google-map-container'
								ref={mapRef}
								className='google-map-editor__map'
								style={{
									width: "100%",
									height: "300px",
								}}
							/>
						)}
					</div>
				</div>
			</div>
		</>
	);
} 
