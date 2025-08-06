import { registerBlockType } from "@wordpress/blocks";

import Edit from "./edit";
import "./style.scss";

// Custom calendar table icon for block registration
const calendarTableIcon = (
	<svg 
		width="24" 
		height="24" 
		viewBox="0 0 24 24" 
		fill="none" 
		xmlns="http://www.w3.org/2000/svg"
	>
		{/* Table structure */}
		<rect x="2" y="4" width="20" height="16" rx="1" stroke="currentColor" strokeWidth="1.5" fill="none"/>
		
		{/* Header row */}
		<line x1="2" y1="8" x2="22" y2="8" stroke="currentColor" strokeWidth="1.5"/>
		
		{/* Vertical dividers for columns */}
		<line x1="6" y1="4" x2="6" y2="20" stroke="currentColor" strokeWidth="1"/>
		<line x1="10" y1="4" x2="10" y2="20" stroke="currentColor" strokeWidth="1"/>
		<line x1="14" y1="4" x2="14" y2="20" stroke="currentColor" strokeWidth="1"/>
		<line x1="18" y1="4" x2="18" y2="20" stroke="currentColor" strokeWidth="1"/>
		
		{/* Horizontal dividers for rows */}
		<line x1="2" y1="12" x2="22" y2="12" stroke="currentColor" strokeWidth="1"/>
		<line x1="2" y1="16" x2="22" y2="16" stroke="currentColor" strokeWidth="1"/>
		
		{/* Availability indicators - colored squares */}
		<rect x="3" y="9" width="2" height="2" fill="#c5c951" rx="0.5"/> {/* Available */}
		<rect x="7" y="9" width="2" height="2" fill="#e3634b" rx="0.5"/> {/* Booked */}
		<rect x="11" y="9" width="2" height="2" fill="#c5c951" rx="0.5"/> {/* Available */}
		<rect x="15" y="9" width="2" height="2" fill="#c5c951" rx="0.5"/> {/* Available */}
		<rect x="19" y="9" width="2" height="2" fill="#e3634b" rx="0.5"/> {/* Booked */}
		
		<rect x="3" y="13" width="2" height="2" fill="#e3634b" rx="0.5"/> {/* Booked */}
		<rect x="7" y="13" width="2" height="2" fill="#c5c951" rx="0.5"/> {/* Available */}
		<rect x="11" y="13" width="2" height="2" fill="#e3634b" rx="0.5"/> {/* Booked */}
		<rect x="15" y="13" width="2" height="2" fill="#c5c951" rx="0.5"/> {/* Available */}
		<rect x="19" y="13" width="2" height="2" fill="#c5c951" rx="0.5"/> {/* Available */}
		
		{/* Calendar indicator - small calendar icon in top corner */}
		<rect x="16" y="1" width="6" height="5" rx="0.5" stroke="currentColor" strokeWidth="1" fill="none"/>
		<line x1="17.5" y1="0.5" x2="17.5" y2="2.5" stroke="currentColor" strokeWidth="1"/>
		<line x1="20.5" y1="0.5" x2="20.5" y2="2.5" stroke="currentColor" strokeWidth="1"/>
		<line x1="16" y1="3.5" x2="22" y2="3.5" stroke="currentColor" strokeWidth="0.5"/>
	</svg>
);

registerBlockType("kate-toms-core/house-calendar-availability", {
	icon: calendarTableIcon,
	edit: Edit,
});
