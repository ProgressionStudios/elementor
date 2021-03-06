<?php
namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Frontend {

	private $google_fonts = [];
	private $registered_fonts = [];
	private $google_early_access_fonts = [];

	private $_is_frontend_mode = false;
	private $_has_elementor_in_page = false;

	public function init() {
		if ( Plugin::$instance->editor->is_edit_mode() || Plugin::$instance->preview->is_preview_mode() ) {
			return;
		}

		$this->_is_frontend_mode = true;
		$this->_has_elementor_in_page = Plugin::$instance->db->has_elementor_in_post( get_the_ID() );

		add_filter( 'body_class', [ $this, 'body_class' ] );

		if ( $this->_has_elementor_in_page ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		}

		add_action( 'wp_head', [ $this, 'print_google_fonts' ] );
		add_action( 'wp_footer', [ $this, 'wp_footer' ] );

		// Add Edit with the Elementor in Admin Bar
		add_action( 'admin_bar_menu', [ $this, 'add_menu_in_admin_bar' ], 200 );
	}

	protected function _print_elements( $elements_data ) {
		foreach ( $elements_data as $element_data ) {
			$element = Plugin::$instance->elements_manager->create_element_instance( $element_data );

			if ( ! $element ) {
				continue;
			}

			$element->print_element();
		}
	}

	public function body_class( $classes = [] ) {
		$classes[] = 'elementor-default';
		if ( is_singular() && 'builder' === Plugin::$instance->db->get_edit_mode( get_the_ID() ) ) {
			$classes[] = 'elementor-page';
		}
		return $classes;
	}

	public function register_scripts() {
		$suffix = Utils::is_script_debug() ? '' : '.min';

		wp_register_script(
			'elementor-waypoints',
			ELEMENTOR_ASSETS_URL . 'lib/waypoints/waypoints' . $suffix . '.js',
			[
				'jquery',
			],
			'4.0.2',
			true
		);

		wp_register_script(
			'imagesloaded',
			ELEMENTOR_ASSETS_URL . 'lib/imagesloaded/imagesloaded' . $suffix . '.js',
			[
				'jquery',
			],
			'4.1.0',
			true
		);

		wp_register_script(
			'jquery-numerator',
			ELEMENTOR_ASSETS_URL . 'lib/jquery-numerator/jquery-numerator' . $suffix . '.js',
			[
				'jquery',
			],
			'0.2.1',
			true
		);

		wp_register_script(
			'jquery-slick',
			ELEMENTOR_ASSETS_URL . 'lib/slick/slick' . $suffix . '.js',
			[
				'jquery',
			],
			'1.6.0',
			true
		);

		wp_register_script(
			'elementor-frontend',
			ELEMENTOR_ASSETS_URL . 'js/frontend' . $suffix . '.js',
			[
				'elementor-waypoints',
				'jquery-numerator',
				'imagesloaded',
				'jquery-slick',
			],
			ELEMENTOR_VERSION,
			true
		);
	}

	public function register_styles() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		$direction_suffix = is_rtl() ? '-rtl' : '';

		wp_register_style(
			'elementor-icons',
			ELEMENTOR_ASSETS_URL . 'lib/eicons/css/elementor-icons' . $suffix . '.css',
			[],
			ELEMENTOR_VERSION
		);

		wp_register_style(
			'font-awesome',
			ELEMENTOR_ASSETS_URL . 'lib/font-awesome/css/font-awesome' . $suffix . '.css',
			[],
			'4.7.0'
		);

		wp_register_style(
			'elementor-animations',
			ELEMENTOR_ASSETS_URL . 'css/animations.min.css',
			[],
			ELEMENTOR_VERSION
		);

		wp_register_style(
			'elementor-frontend',
			ELEMENTOR_ASSETS_URL . 'css/frontend' . $direction_suffix . $suffix . '.css',
			[],
			ELEMENTOR_VERSION
		);
	}

	public function enqueue_scripts() {
		Utils::do_action_deprecated( 'elementor/frontend/enqueue_scripts/before', [], '1.0.10', 'elementor/frontend/before_enqueue_scripts' );

		do_action( 'elementor/frontend/before_enqueue_scripts' );

		wp_enqueue_script( 'elementor-frontend' );

		wp_localize_script(
			'elementor-frontend',
			'elementorFrontendConfig', [
				'isEditMode' => Plugin::$instance->editor->is_edit_mode(),
				'stretchedSectionContainer' => get_option( 'elementor_stretched_section_container', '' ),
				'is_rtl' => is_rtl(),
			]
		);
	}

	public function enqueue_styles() {
		wp_enqueue_style( 'elementor-icons' );
		wp_enqueue_style( 'font-awesome' );
		wp_enqueue_style( 'elementor-animations' );
		wp_enqueue_style( 'elementor-frontend' );

		if ( ! Plugin::$instance->preview->is_preview_mode() ) {
			$this->parse_global_css_code();

			$css_file = new Post_CSS_File( get_the_ID() );
			$css_file->enqueue();
		}
	}

	/**
	 * Handle style that do not printed in header
	 */
	public function wp_footer() {
		if ( ! $this->_has_elementor_in_page ) {
			return;
		}

		$this->enqueue_styles();
		$this->enqueue_scripts();

		$this->print_google_fonts();
	}

	public function print_google_fonts() {
		// Print used fonts
		if ( ! empty( $this->google_fonts ) ) {
			foreach ( $this->google_fonts as &$font ) {
				$font = str_replace( ' ', '+', $font ) . ':100,100italic,200,200italic,300,300italic,400,400italic,500,500italic,600,600italic,700,700italic,800,800italic,900,900italic';
			}

			$fonts_url = sprintf( 'https://fonts.googleapis.com/css?family=%s', implode( '|', $this->google_fonts ) );

			$subsets = [
				'ru_RU' => 'cyrillic',
				'bg_BG' => 'cyrillic',
				'he_IL' => 'hebrew',
				'el' => 'greek',
				'vi' => 'vietnamese',
				'uk' => 'cyrillic',
			];
			$locale = get_locale();

			if ( isset( $subsets[ $locale ] ) ) {
				$fonts_url .= '&subset=' . $subsets[ $locale ];
			}

			echo '<link rel="stylesheet" type="text/css" href="' . $fonts_url . '">';
			$this->google_fonts = [];
		}

		if ( ! empty( $this->google_early_access_fonts ) ) {
			foreach ( $this->google_early_access_fonts as $current_font ) {
				printf( '<link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/earlyaccess/%s.css">', strtolower( str_replace( ' ', '', $current_font ) ) );
			}
			$this->google_early_access_fonts = [];
		}
	}

	public function enqueue_font( $font ) {
		$font_type = Fonts::get_font_type( $font );
		$cache_id = $font_type . $font;

		if ( in_array( $cache_id, $this->registered_fonts ) ) {
			return;
		}

		switch ( $font_type ) {
			case Fonts::GOOGLE :
				if ( ! in_array( $font, $this->google_fonts ) )
					$this->google_fonts[] = $font;
				break;

			case Fonts::EARLYACCESS :
				if ( ! in_array( $font, $this->google_early_access_fonts ) )
					$this->google_early_access_fonts[] = $font;
				break;
		}

		$this->registered_fonts[] = $cache_id;
	}

	protected function parse_global_css_code() {
		$scheme_css_file = new Global_CSS_File();

		$scheme_css_file->enqueue();
	}

	public function apply_builder_in_content( $content ) {
		// Remove the filter itself in order to allow other `the_content` in the elements
		remove_filter( 'the_content', [ $this, 'apply_builder_in_content' ] );

		if ( ! $this->_is_frontend_mode )
			return $content;

		$post_id = get_the_ID();
		$builder_content = $this->get_builder_content( $post_id );

		if ( ! empty( $builder_content ) ) {
			$content = $builder_content;
		}

		// Add the filter again for other `the_content` calls
		add_filter( 'the_content', [ $this, 'apply_builder_in_content' ] );

		return $content;
	}

	public function get_builder_content( $post_id, $with_css = false ) {
		if ( post_password_required( $post_id ) ) {
			return '';
		}

		$edit_mode = Plugin::$instance->db->get_edit_mode( $post_id );
		if ( 'builder' !== $edit_mode ) {
			return '';
		}

		$data = Plugin::$instance->db->get_plain_editor( $post_id );
		$data = apply_filters( 'elementor/frontend/builder_content_data', $data, $post_id );

		if ( empty( $data ) ) {
			return '';
		}

		$css_file = new Post_CSS_File( $post_id );
		$css_file->enqueue();

		ob_start();

		// Handle JS and Customizer requests, with css inline
		if ( is_customize_preview() || Utils::is_ajax() ) {
			$with_css = true;
		}

		if ( $with_css ) {
			echo '<style>' . $css_file->get_css() . '</style>';
		}

		?>
		<div class="elementor elementor-<?php echo $post_id; ?>">
			<div class="elementor-inner">
				<div class="elementor-section-wrap">
					<?php $this->_print_elements( $data ); ?>
				</div>
			</div>
		</div>
		<?php
		$content = apply_filters( 'elementor/frontend/the_content', ob_get_clean() );

		if ( ! empty( $content ) ) {
			$this->_has_elementor_in_page = true;
		}

		return $content;
	}

	function add_menu_in_admin_bar( \WP_Admin_Bar $wp_admin_bar ) {
		$post_id = get_the_ID();
		$is_not_builder_mode = ! is_singular() || ! User::is_current_user_can_edit( $post_id ) || 'builder' !== Plugin::$instance->db->get_edit_mode( $post_id );

		if ( $is_not_builder_mode ) {
			return;
		}

		$wp_admin_bar->add_node( [
			'id' => 'elementor_edit_page',
			'title' => __( 'Edit with Elementor', 'elementor' ),
			'href' => Utils::get_edit_link( $post_id ),
		] );
	}

	public function get_builder_content_for_display( $post_id ) {
		if ( ! get_post( $post_id ) ) {
			return '';
		}

		// Avoid recursion
		if ( get_the_ID() === (int) $post_id ) {
			$content = '';
			if ( Plugin::$instance->editor->is_edit_mode() ) {
				$content = '<div class="elementor-alert elementor-alert-danger">' . __( 'Invalid Data: The Template ID cannot be the same as the currently edited template. Please choose a different one.', 'elementor' ) . '</div>';
			}

			return $content;
		}

		// Set edit mode as false, so don't render settings and etc. use the $is_edit_mode to indicate if we need the css inline
		$is_edit_mode = Plugin::$instance->editor->is_edit_mode();
		Plugin::$instance->editor->set_edit_mode( false );

		// Change the global post to current library post, so widgets can use `get_the_ID` and other post data
		if ( isset( $GLOBALS['post'] ) ) {
			$global_post = $GLOBALS['post'];
		}

		$GLOBALS['post'] = get_post( $post_id );

		$content = $this->get_builder_content( $post_id, $is_edit_mode );

		// Restore global post
		if ( isset( $global_post ) ) {
			$GLOBALS['post'] = $global_post;
		} else {
			unset( $GLOBALS['post'] );
		}

		// Restore edit mode state
		Plugin::$instance->editor->set_edit_mode( $is_edit_mode );

		return $content;
	}

	public function __construct() {
		// We don't need this class in admin side, but in AJAX requests
		if ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}

		add_action( 'template_redirect', [ $this, 'init' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_scripts' ], 5 );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_styles' ], 5 );
		add_filter( 'the_content', [ $this, 'apply_builder_in_content' ] );
	}
}
