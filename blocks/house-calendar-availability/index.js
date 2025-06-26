import { registerBlockType } from "@wordpress/blocks";
import { calendar } from "@wordpress/icons";

import Edit from "./edit";
import "./style.scss";

registerBlockType("kate-toms-core/house-calendar-availability", {
	icon: calendar,
	edit: Edit,
});
