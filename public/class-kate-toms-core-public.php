<?php

/**
 * The public-specific functionality of the plugin.
 *
 * @link       https://elliottrichmond.co.uk
 * @since      1.0.0
 *
 * @package    Kate_Toms_Core
 * @subpackage Kate_Toms_Core/public
 */

/**
 * The public-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-specific stylesheet and JavaScript.
 *
 * @package    Kate_Toms_Core
 * @subpackage Kate_Toms_Core/public
 * @author     Elliott Richmond <elliott@squareonemd.co.uk>
 */
class Kate_Toms_Core_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the public area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Kate_Toms_Core_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Kate_Toms_Core_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/kate-toms-core-public.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the public area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Kate_Toms_Core_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Kate_Toms_Core_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/kate-toms-core-public.js', array( 'jquery' ), $this->version, false );
	}

	/**
	 * Filter out bedroom-related terms from the feature taxonomy.
	 * Only applies on /houses/ pages.
	 *
	 * @since    1.0.0
	 * @param    array  $terms       Array of term objects.
	 * @param    array  $taxonomies  Array of taxonomy names.
	 * @param    array  $args        Array of get_terms() arguments.
	 * @return   array  Filtered array of term objects.
	 */
	public function filter_bedroom_terms( $terms, $taxonomies, $args ) {
		// Only apply filter on /houses/ pages
		if ( strpos( $_SERVER['REQUEST_URI'], '/houses/' ) === false ) {
			return $terms;
		}

		// Only filter the feature taxonomy
		if ( in_array( 'feature', $taxonomies ) ) {
			$terms = array_filter(
				$terms,
				function( $term ) {
					return stripos( $term->name, 'bedroom' ) === false;
				}
			);
		}

		return $terms;
	}



	

	/**
	 * Helper Functions
	 *
	 * The functions below are related to testing or can be considered
	 * as temporary helper functions for development purposes. They
	 * may potentially be removed in future versions of the plugin.
	 *
	 * @since 1.0.0
	 */

	/**
	 * Outputs the BugHerd tracking script in the frontend.
	 *
	 * Note: This is a development tool and should be properly managed via wp_enqueue_script().
	 * Consider refactoring to use WordPress enqueue system for better practice.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function bugherd_script() {
		echo '<script type="text/javascript" src="https://www.bugherd.com/sidebarv2.js?apikey=8je1j3guc7qdsjlkvgxhyq" async="true"></script>';
	}

	/**
	 * Outputs the Trustpilot bootstrap script in the head.
	 *
	 * Required for Trustpilot widgets to render properly on the frontend.
	 * The script must be loaded in the head before widget divs are rendered.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function trustpilot_script() {
		echo "<!-- TrustBox script -->\n";
		echo "<script type='text/javascript' src='//widget.trustpilot.com/bootstrap/v5/tp.widget.bootstrap.min.js' async></script>\n";
		echo "<!-- End TrustBox script -->";
	}

	/**
	 * Outputs the TikTok tracking script in the head.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function tiktoc_script() {
		echo "<!-- TikTok tracking script -->\n";
		echo '<script>
	!function (w, d, t) {
	  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};

	  ttq.load("COL543JC77U8K5AP3PPG");
	  ttq.page();
	}(window, document, "ttq");
	</script>';
		echo "<!-- End TikTok tracking script -->";
	}

	/**
	 * Outputs the Facebook Pixel code in the head.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function kt_facebook_pixel_header_code() {
		echo "<!-- Meta Pixel Code -->
		<script>
		!function(f,b,e,v,n,t,s)
		{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
		n.callMethod.apply(n,arguments):n.queue.push(arguments)};
		if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
		n.queue=[];t=b.createElement(e);t.async=!0;
		t.src=v;s=b.getElementsByTagName(e)[0];
		s.parentNode.insertBefore(t,s)}(window, document,'script',
		'https://connect.facebook.net/en_US/fbevents.js');
		fbq('init', '1409335782416862');
		fbq('track', 'PageView');
		</script>
		<noscript><img height='1' width='1' style='display:none'
		src='https://www.facebook.com/tr?id=1409335782416862&ev=PageView&noscript=1'
		/></noscript>
		<!-- End Meta Pixel Code -->";
	}

	/**
	 * Outputs the Hive code in the head.
	 *
	 * @return void
	 */
	public function kt_hive_code_header_code() {
		echo "<script>!function () { var a = document.createElement('script'); a.type = 'text/javascript', a.async = !0, a.src = 'https://tracking.hivecloud.net/client-scripts/kateandtoms.min.js'; var b = document.getElementsByTagName('script')[0]; b.parentNode.insertBefore(a, b) }();</script>";
	}

	/**
	 * Outputs the LinkedIn tracking script in the footer.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function linkedin_script() {
		$output = '<!-- Begin LinkedIn -->
		<script type="text/javascript"> _linkedin_partner_id = "5415716"; window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || []; window._linkedin_data_partner_ids.push(_linkedin_partner_id); </script><script type="text/javascript"> (function(l) { if (!l){window.lintrk = function(a,b){window.lintrk.q.push([a,b])}; window.lintrk.q=[]} var s = document.getElementsByTagName("script")[0]; var b = document.createElement("script"); b.type = "text/javascript";b.async = true; b.src = "https://snap.licdn.com/li.lms-analytics/insight.min.js"; s.parentNode.insertBefore(b, s);})(window.lintrk); </script> <noscript> <img height="1" width="1" style="display:none;" alt="" src="https://px.ads.linkedin.com/collect/?pid=5415716&fmt=gif" /> </noscript>
		<!-- End LinkedIn -->';
		echo $output;
	}

	/**
	 * Outputs the Google Tag Manager script in the head.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function google_header_tag_manager_script() {
		$gtm_code  = "<!-- Google Tag Manager -->
		<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
		new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
		j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
		'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
		})(window,document,'script','dataLayer','GTM-WNT2MLS');</script>
		<!-- End Google Tag Manager -->";
		$gtm_code .= "<!-- Google tag (gtag.js) --> <script async src='https://www.googletagmanager.com/gtag/js?id=AW-1068089463'></script> <script> window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag('js', new Date()); gtag('config', 'AW-1068089463'); </script>";
		echo $gtm_code;
	}

	/**
	 * Outputs the Google Tag Manager (noscript) script in the footer.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function google_footer_tag_manager_script() {
		echo '<!-- Google Tag Manager (noscript) -->
		<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-WNT2MLS"
		height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
		<!-- End Google Tag Manager (noscript) -->';
	}

	/**
	 * Modifies featured image HTML to handle URL differences between environments.
	 *
	 * @since 1.0.0
	 * @param string $html               The featured image HTML.
	 * @param int    $post_id           The post ID.
	 * @param int    $post_thumbnail_id The post thumbnail ID.
	 * @param string $size              The requested image size.
	 * @param array  $attr              Array of image attributes.
	 * @return string Modified featured image HTML.
	 */
	public function modify_featured_image_html_local_staging_production( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
		// Modify the HTML here
		$html = str_replace(
			[ 'bigholidayhomes.co.uk', 'http://kateandtomsblocks.test', 'blogs.dir/11/files'],
			[ 'kateandtoms.com', 'https://kateandtoms.com', 'uploads'],
			$html
		);
		return $html;
	}

	/**
	 * Replaces the domain in image srcset URLs with the CDN domain.
	 *
	 * @since 1.0.0
	 * @param array  $sources      The array of image sources for the srcset.
	 * @param array  $size_array   Array of width and height values in pixels (in that order).
	 * @param string $image_src    The image source URL.
	 * @param array  $image_meta   The image meta data.
	 * @param int    $attachment_id The image attachment ID.
	 * @return array Modified array of image sources with CDN URLs.
	 */
	public function kate_toms_replace_image_srcset_url( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		foreach ( $sources as &$source ) {
			$source['url'] = str_replace( '//kateandtomsblocks.test', '//kateandtoms.com', $source['url'] );
		}
		return $sources;
	}

}
