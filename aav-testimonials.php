<?php
/**
 * Plugin Name:       AAV — Client Reviews (Testimonials)
 * Description:        Custom "Review" post type with Destination/Experience taxonomies, per-review photo and experience CTA, in-dashboard CSV import, and shortcodes for the homepage section and the "Client Stories" page. Editorial styling in the AAV bordeaux palette. Theme-independent.
 * Version:           1.5.4
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Biolay Group
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aav
 * Domain Path:       /languages
 * GitHub Plugin URI: biolay-group/aav-testimonials
 * Primary Branch:    main
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'AAV_VERSION', '1.5.3' );

/* ================================================================== *
 * 0. Traductions
 * ================================================================== */
add_action( 'init', function () {
	load_plugin_textdomain( 'aav', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}, 1 );

/* ================================================================== *
 * 1. Expériences officielles du site AAV (slug => [nom, URL])
 *    Sert à créer les termes et à construire le CTA de chaque avis.
 *    NB : « Spa & Wellness » a une URL atypique sur le site.
 * ================================================================== */
function aav_experiences_map() {
	return apply_filters( 'aav_experiences_map', array(
		'wine'                   => array( 'Wine',                   '/experiences/wine/' ),
		'gourmet'                => array( 'Gourmet',                '/experiences/gourmet/' ),
		'fashion-and-shopping'   => array( 'Fashion & Shopping',     '/experiences/fashion-and-shopping/' ),
		'architecture-and-gardens'=> array( 'Architecture & Gardens', '/experiences/architecture-and-gardens/' ),
		'art-and-history'        => array( 'Art & History',          '/experiences/art-and-history/' ),
		'markets'                => array( 'Markets',                '/experiences/markets/' ),
		'chateaux-and-castles'   => array( 'Chateaux & Castles',     '/experiences/chateaux-and-castles/' ),
		'sport-in-france'        => array( 'Sport in France',        '/experiences/sport-in-france/' ),
		'spa-and-wellness'       => array( 'Spa & Wellness',         '/experiences/chateaux-and-castles-1/' ),
		'skiing-in-france'       => array( 'Skiing',                 '/experiences/skiing-in-france/' ),
		'golf-in-france'         => array( 'Golf',                   '/experiences/golf-in-france/' ),
	) );
}

/** URL publique d'un terme « Experience ». */
function aav_experience_url( $term ) {
	$map = aav_experiences_map();
	if ( isset( $map[ $term->slug ] ) ) return home_url( $map[ $term->slug ][1] );
	// Repli : URL déduite du slug, ou surcharge manuelle via un champ de terme.
	$custom = get_term_meta( $term->term_id, 'aav_experience_url', true );
	if ( $custom ) return $custom;
	return home_url( '/experiences/' . $term->slug . '/' );
}

/* ================================================================== *
 * 2. CPT + taxonomies (aav_occasion conservée : les données restent)
 * ================================================================== */
add_action( 'init', function () {

	register_post_type( 'aav_testimonial', array(
		'labels' => array(
			'name'          => __( 'Reviews', 'aav' ),
			'singular_name' => __( 'Review', 'aav' ),
			'add_new'       => __( 'Add Review', 'aav' ),
			'add_new_item'  => __( 'Add Review', 'aav' ),
			'edit_item'     => __( 'Edit Review', 'aav' ),
			'new_item'      => __( 'New Review', 'aav' ),
			'search_items'  => __( 'Search Reviews', 'aav' ),
			'not_found'     => __( 'No reviews found', 'aav' ),
			'menu_name'     => __( 'Client Reviews', 'aav' ),
		),
		'public'        => false,
		'show_ui'       => true,
		'show_in_menu'  => true,
		'show_in_rest'  => true,
		'menu_icon'     => 'dashicons-format-quote',
		'menu_position' => 26,
		'supports'      => array( 'title' ),
		'has_archive'   => false,
		'rewrite'       => false,
	) );

	register_taxonomy( 'aav_destination', 'aav_testimonial', array(
		'labels' => array(
			'name'          => __( 'Destinations', 'aav' ),
			'singular_name' => __( 'Destination', 'aav' ),
			'menu_name'     => __( 'Destinations', 'aav' ),
			'add_new_item'  => __( 'Add Destination', 'aav' ),
		),
		'public'            => false,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'hierarchical'      => true,
		'rewrite'           => false,
	) );

	// Clé technique inchangée (aav_occasion) => aucune donnée perdue ; libellé = Experiences.
	register_taxonomy( 'aav_occasion', 'aav_testimonial', array(
		'labels' => array(
			'name'          => __( 'Experiences', 'aav' ),
			'singular_name' => __( 'Experience', 'aav' ),
			'menu_name'     => __( 'Experiences', 'aav' ),
			'add_new_item'  => __( 'Add Experience', 'aav' ),
			'all_items'     => __( 'All Experiences', 'aav' ),
		),
		'public'            => false,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'hierarchical'      => true,
		'rewrite'           => false,
	) );
} );

/* ================================================================== *
 * 3. Création automatique des 11 expériences du site (à l'activation)
 * ================================================================== */
function aav_seed_experiences() {
	foreach ( aav_experiences_map() as $slug => $data ) {
		if ( ! term_exists( $slug, 'aav_occasion' ) ) {
			wp_insert_term( $data[0], 'aav_occasion', array( 'slug' => $slug ) );
		}
	}
}
register_activation_hook( __FILE__, function () {
	// Les taxonomies doivent exister avant d'insérer les termes.
	do_action( 'init' );
	aav_seed_experiences();
} );

/* ================================================================== *
 * 4. Champs ACF : citation, photo, lieux, année, expérience CTA, mise en avant
 * ================================================================== */
add_action( 'acf/init', function () {

	if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

	acf_add_local_field_group( array(
		'key'    => 'group_aav_testimonial',
		'title'  => __( 'Review details', 'aav' ),
		'fields' => array(
			array( 'key' => 'field_aav_quote',     'label' => __( 'Quote', 'aav' ),                'name' => 'quote',         'type' => 'textarea', 'rows' => 4, 'required' => 1, 'instructions' => __( 'The review text, without quotation marks.', 'aav' ) ),
			array( 'key' => 'field_aav_photo',     'label' => __( 'Photo', 'aav' ),                'name' => 'photo',         'type' => 'image', 'return_format' => 'id', 'preview_size' => 'medium', 'library' => 'all', 'instructions' => __( 'Optional. Shown at the top of the card — a destination or trip photo works best.', 'aav' ) ),
			array( 'key' => 'field_aav_locations', 'label' => __( 'Locations (display)', 'aav' ),  'name' => 'locations',     'type' => 'text',                'instructions' => __( 'Shown under the name, e.g. “Paris • Brittany • London”.', 'aav' ) ),
			array( 'key' => 'field_aav_year',      'label' => __( 'Year', 'aav' ),                 'name' => 'year',          'type' => 'text',                'instructions' => __( 'E.g. “2025”.', 'aav' ) ),
			array( 'key' => 'field_aav_featured',  'label' => __( 'Feature on homepage', 'aav' ),  'name' => 'featured_home', 'type' => 'true_false', 'ui' => 1, 'instructions' => __( 'Tick up to 6 reviews for the homepage.', 'aav' ) ),
			array( 'key' => 'field_aav_cta_url',   'label' => __( 'Custom CTA link (optional)', 'aav' ), 'name' => 'cta_url', 'type' => 'url',    'instructions' => __( 'Leave empty to link automatically to the first Experience selected below.', 'aav' ) ),
		),
		'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'aav_testimonial' ) ) ),
	) );
} );

/* ================================================================== *
 * 5. Colonnes admin
 * ================================================================== */
add_filter( 'manage_aav_testimonial_posts_columns', function ( $cols ) {
	return array(
		'cb'                       => isset( $cols['cb'] ) ? $cols['cb'] : '',
		'aav_thumb'                => __( 'Photo', 'aav' ),
		'title'                    => __( 'Client', 'aav' ),
		'aav_year'                 => __( 'Year', 'aav' ),
		'aav_featured'             => __( '★ Home', 'aav' ),
		'taxonomy-aav_destination' => __( 'Destinations', 'aav' ),
		'taxonomy-aav_occasion'    => __( 'Experiences', 'aav' ),
	);
} );

add_action( 'manage_aav_testimonial_posts_custom_column', function ( $col, $post_id ) {
	$get = function ( $k ) use ( $post_id ) { return function_exists( 'get_field' ) ? get_field( $k, $post_id ) : get_post_meta( $post_id, $k, true ); };
	if ( 'aav_year' === $col ) {
		echo esc_html( $get( 'year' ) );
	} elseif ( 'aav_featured' === $col ) {
		echo $get( 'featured_home' ) ? '★' : '—';
	} elseif ( 'aav_thumb' === $col ) {
		$img = $get( 'photo' );
		if ( $img ) {
			echo wp_get_attachment_image( (int) $img, array( 60, 60 ), false, array( 'style' => 'object-fit:cover;border-radius:2px;' ) );
		} else {
			echo '<span style="color:#c3c4c7;">—</span>';
		}
	}
}, 10, 2 );

add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-aav_testimonial' !== $screen->id ) return;
	$q = new WP_Query( array( 'post_type' => 'aav_testimonial', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => 'featured_home', 'meta_value' => '1', 'no_found_rows' => true ) );
	if ( $q->post_count > 6 ) {
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html( sprintf(
				/* translators: %d: number of featured reviews */
				__( '%d reviews are featured on the homepage. Only 6 are shown — please untick some.', 'aav' ),
				(int) $q->post_count
			) )
		);
	}
} );

/* ================================================================== *
 * 6. IMPORT CSV (colonnes : + photo_url, experience)
 * ================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=aav_testimonial',
		__( 'Import reviews (CSV)', 'aav' ),
		__( 'Import (CSV)', 'aav' ),
		'manage_options',
		'aav-import',
		'aav_render_import_page'
	);
} );

function aav_render_import_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$report = null;
	if ( isset( $_POST['aav_import_nonce'] ) && wp_verify_nonce( $_POST['aav_import_nonce'], 'aav_import' ) && ! empty( $_FILES['aav_csv']['tmp_name'] ) ) {
		$report = aav_do_import( $_FILES['aav_csv']['tmp_name'] );
	}
	$exp = aav_experiences_map();
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Import reviews (CSV)', 'aav' ); ?></h1>
		<p><?php printf(
			/* translators: %s: CSV column list */
			esc_html__( 'Expected columns: %s. Separate multiple values with a semicolon.', 'aav' ),
			'<code>quote,author,locations,year,destination,experience,photo_url,featured_home</code>'
		); ?></p>
		<p><?php echo esc_html__( 'A review that already exists (same client + year) is updated, not duplicated.', 'aav' ); ?></p>
		<p><strong><?php echo esc_html__( 'Available experience slugs:', 'aav' ); ?></strong> <code><?php echo esc_html( implode( ', ', array_keys( $exp ) ) ); ?></code></p>
		<?php if ( $report ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( sprintf(
				/* translators: 1: created, 2: updated */
				__( 'Done: %1$d created, %2$d updated.', 'aav' ),
				$report['created'], $report['updated']
			) ); ?></p></div>
		<?php endif; ?>
		<form method="post" enctype="multipart/form-data" style="margin-top:16px;">
			<?php wp_nonce_field( 'aav_import', 'aav_import_nonce' ); ?>
			<input type="file" name="aav_csv" accept=".csv" required />
			<?php submit_button( __( 'Import', 'aav' ), 'primary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}

function aav_do_import( $path ) {
	$created = 0; $updated = 0;
	$fh = fopen( $path, 'r' );
	if ( ! $fh ) return array( 'created' => 0, 'updated' => 0 );

	$header = fgetcsv( $fh );
	if ( ! $header ) { fclose( $fh ); return array( 'created' => 0, 'updated' => 0 ); }
	$header = array_map( 'trim', $header );
	if ( isset( $header[0] ) ) $header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] );

	$exp_map = aav_experiences_map();

	while ( ( $row = fgetcsv( $fh ) ) !== false ) {
		if ( count( array_filter( $row ) ) === 0 ) continue;
		$data = array_combine( $header, array_pad( $row, count( $header ), '' ) );
		$val  = function ( $k ) use ( $data ) { return isset( $data[ $k ] ) ? trim( $data[ $k ] ) : ''; };

		$author = $val( 'author' );
		$year   = $val( 'year' );
		if ( '' === $author ) continue;

		$existing = get_posts( array(
			'post_type'   => 'aav_testimonial',
			'title'       => $author,
			'numberposts' => 1,
			'post_status' => 'any',
			'fields'      => 'ids',
			'meta_query'  => array( array( 'key' => 'year', 'value' => $year ) ),
		) );

		$post_id = $existing ? $existing[0] : wp_insert_post( array(
			'post_type'   => 'aav_testimonial',
			'post_title'  => $author,
			'post_status' => 'publish',
		) );
		if ( is_wp_error( $post_id ) || ! $post_id ) continue;

		$featured = in_array( strtolower( $val( 'featured_home' ) ), array( 'yes', '1', 'true', 'oui' ), true );

		$set = function ( $k, $v ) use ( $post_id ) {
			if ( function_exists( 'update_field' ) ) { update_field( $k, $v, $post_id ); }
			else { update_post_meta( $post_id, $k, $v ); }
		};
		$set( 'quote', $val( 'quote' ) );
		$set( 'locations', $val( 'locations' ) );
		$set( 'year', $year );
		$set( 'featured_home', $featured ? 1 : 0 );

		// Photo : ID de média, ou URL déjà présente dans la médiathèque.
		$photo = $val( 'photo_url' );
		if ( $photo ) {
			$att_id = is_numeric( $photo ) ? (int) $photo : attachment_url_to_postid( $photo );
			if ( $att_id ) $set( 'photo', $att_id );
		}

		// Destinations.
		$terms = array_filter( array_map( 'trim', explode( ';', $val( 'destination' ) ) ) );
		if ( $terms ) wp_set_object_terms( $post_id, $terms, 'aav_destination', false );

		// Expériences : accepte les slugs officiels ou les noms.
		$raw = array_filter( array_map( 'trim', explode( ';', $val( 'experience' ) ) ) );
		if ( $raw ) {
			$ids = array();
			foreach ( $raw as $one ) {
				$slug = sanitize_title( $one );
				if ( ! isset( $exp_map[ $slug ] ) ) {
					// tente une correspondance par nom
					foreach ( $exp_map as $s => $d ) {
						if ( strtolower( $d[0] ) === strtolower( $one ) ) { $slug = $s; break; }
					}
				}
				$term = get_term_by( 'slug', $slug, 'aav_occasion' );
				if ( ! $term && isset( $exp_map[ $slug ] ) ) {
					$new = wp_insert_term( $exp_map[ $slug ][0], 'aav_occasion', array( 'slug' => $slug ) );
					if ( ! is_wp_error( $new ) ) $ids[] = (int) $new['term_id'];
				} elseif ( $term ) {
					$ids[] = (int) $term->term_id;
				}
			}
			if ( $ids ) wp_set_object_terms( $post_id, $ids, 'aav_occasion', false );
		}

		$existing ? $updated++ : $created++;
	}
	fclose( $fh );
	return array( 'created' => $created, 'updated' => $updated );
}

/* ================================================================== *
 * 6bis. RÉGLAGES (Client Reviews → Settings) : disposition
 * ================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=aav_testimonial',
		__( 'Reviews settings', 'aav' ),
		__( 'Settings', 'aav' ),
		'manage_options',
		'aav-settings',
		'aav_render_settings_page'
	);
} );

add_action( 'admin_init', function () {
	register_setting( 'aav_settings', 'aav_layout', array(
		'type'              => 'string',
		'default'           => 'grid',
		'sanitize_callback' => function ( $v ) { return in_array( $v, array( 'grid', 'editorial' ), true ) ? $v : 'grid'; },
	) );
} );

/** Disposition active (option, surchargeable par le shortcode). */
function aav_layout() {
	$l = get_option( 'aav_layout', 'grid' );
	return in_array( $l, array( 'grid', 'editorial' ), true ) ? $l : 'grid';
}

function aav_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$current = aav_layout();
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Reviews settings', 'aav' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'aav_settings' ); ?>
			<h2 class="title"><?php echo esc_html__( 'Layout of the reviews', 'aav' ); ?></h2>
			<p><?php echo esc_html__( 'Choose how the review cards are arranged on the “Client Stories” page and on the homepage section.', 'aav' ); ?></p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php echo esc_html__( 'Layout', 'aav' ); ?></th>
					<td>
						<fieldset>
							<label style="display:block;margin-bottom:14px;">
								<input type="radio" name="aav_layout" value="grid" <?php checked( $current, 'grid' ); ?> />
								<strong><?php echo esc_html__( 'Grid', 'aav' ); ?></strong> —
								<?php echo esc_html__( 'Three equal cards per row. Clean and regular.', 'aav' ); ?>
							</label>
							<label style="display:block;">
								<input type="radio" name="aav_layout" value="editorial" <?php checked( $current, 'editorial' ); ?> />
								<strong><?php echo esc_html__( 'Editorial (magazine)', 'aav' ); ?></strong> —
								<?php echo esc_html__( 'Three vertical cards, then one full-width horizontal card, alternating left and right. More rhythm, more premium.', 'aav' ); ?>
							</label>
						</fieldset>
						<p class="description" style="margin-top:14px;">
							<?php echo esc_html__( 'You can also override this on a single page with the shortcode, e.g. [aav_client_stories layout="editorial"].', 'aav' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/* ================================================================== *
 * 7. Rendu d'une carte (photo + citation + CTA expérience)
 * ================================================================== */
function aav_section_head( $eyebrow, $title, $intro ) {
	if ( '' === trim( $eyebrow . $title . $intro ) ) return '';
	$allowed = array( 'em' => array(), 'i' => array(), 'br' => array(), 'strong' => array(), 'span' => array( 'class' => array() ) );
	ob_start(); ?>
	<header class="aav-testimonials__head">
		<?php if ( '' !== $eyebrow ) : ?><span class="aav-testimonials__eyebrow"><?php echo esc_html( $eyebrow ); ?></span><?php endif; ?>
		<?php if ( '' !== $title ) : ?><h2 class="aav-testimonials__title"><?php echo wp_kses( $title, $allowed ); ?></h2><?php endif; ?>
		<?php if ( '' !== $intro ) : ?><p class="aav-testimonials__intro"><?php echo wp_kses( $intro, $allowed ); ?></p><?php endif; ?>
	</header>
	<?php
	return ob_get_clean();
}

function aav_render_card( $post_id, $cta_label = 'Discover the experience' ) {
	$get       = function ( $k ) use ( $post_id ) { return function_exists( 'get_field' ) ? get_field( $k, $post_id ) : get_post_meta( $post_id, $k, true ); };
	$quote     = $get( 'quote' );
	$author    = get_the_title( $post_id );
	$locations = $get( 'locations' );
	$year      = $get( 'year' );
	$photo     = $get( 'photo' );
	$cta_url   = $get( 'cta_url' );

	$dest_terms = wp_get_object_terms( $post_id, 'aav_destination' );
	$exp_terms  = wp_get_object_terms( $post_id, 'aav_occasion' );
	$dest_terms = is_wp_error( $dest_terms ) ? array() : $dest_terms;
	$exp_terms  = is_wp_error( $exp_terms ) ? array() : $exp_terms;

	$dest_slugs = wp_list_pluck( $dest_terms, 'slug' );
	$exp_slugs  = wp_list_pluck( $exp_terms, 'slug' );

	// CTA : lien manuel sinon 1re expérience associée.
	$link = $cta_url;
	$label = $cta_label;
	if ( ! $link && ! empty( $exp_terms ) ) {
		$link  = aav_experience_url( $exp_terms[0] );
		/* Libelle volontairement non traduit : le front AAV est en anglais
		   quelle que soit la locale du site. Personnalisable via le filtre
		   aav_testimonials_cta_label (recoit le gabarit, %s = nom de l'experience). */
		$label = sprintf(
			apply_filters( 'aav_testimonials_cta_label', 'Discover %s' ),
			$exp_terms[0]->name
		);
	}

	$meta = trim( $locations . ( $year ? ' | ' . $year : '' ) );

	ob_start(); ?>
	<article class="aav-card<?php echo $photo ? ' aav-card--photo' : ''; ?>"
		data-destination="<?php echo esc_attr( implode( ' ', $dest_slugs ) ); ?>"
		data-occasion="<?php echo esc_attr( implode( ' ', $exp_slugs ) ); ?>">
		<?php if ( $photo ) : ?>
			<div class="aav-card__media">
				<?php echo wp_get_attachment_image( (int) $photo, 'medium_large', false, array( 'class' => 'aav-card__img', 'loading' => 'lazy', 'alt' => esc_attr( $author ) ) ); ?>
			</div>
		<?php endif; ?>
		<div class="aav-card__body">
			<blockquote class="aav-card__quote"><?php echo esc_html( $quote ); ?></blockquote>
			<div class="aav-card__foot">
				<div class="aav-card__author"><?php echo esc_html( $author ); ?></div>
				<?php if ( $meta ) : ?><div class="aav-card__meta"><?php echo esc_html( $meta ); ?></div><?php endif; ?>
			</div>
		</div>
		<?php if ( $link ) : ?>
			<a class="aav-card__cta" href="<?php echo esc_url( $link ); ?>">
				<span class="aav-card__cta-label"><?php echo esc_html( $label ); ?></span>
				<span class="aav-card__cta-arrow" aria-hidden="true">&rarr;</span>
			</a>
		<?php endif; ?>
	</article>
	<?php
	return ob_get_clean();
}

/* ================================================================== *
 * 8. Shortcode accueil
 * ================================================================== */
add_shortcode( 'aav_home_testimonials', function ( $atts ) {
	$atts = shortcode_atts( array(
		'eyebrow'  => '',
		'headline' => '<em>Travel memories</em><br>in their own words',
		'intro'    => '',
		'cta'      => 'Read More Client Stories',
		'url'      => '/client-stories/',
		'count'    => 6,
		'layout'   => '',
	), $atts, 'aav_home_testimonials' );

	$layout = in_array( $atts['layout'], array( 'grid', 'editorial' ), true ) ? $atts['layout'] : aav_layout();

	$q = new WP_Query( array(
		'post_type'      => 'aav_testimonial',
		'posts_per_page' => (int) $atts['count'],
		'meta_key'       => 'featured_home',
		'meta_value'     => '1',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	) );

	ob_start(); ?>
	<section class="aav-testimonials aav-testimonials--home">
		<?php echo aav_section_head( $atts['eyebrow'], $atts['headline'], $atts['intro'] ); ?>
		<div class="aav-grid aav-grid--3 aav-grid--<?php echo esc_attr( $layout ); ?>">
			<?php while ( $q->have_posts() ) : $q->the_post(); echo aav_render_card( get_the_ID() ); endwhile; wp_reset_postdata(); ?>
		</div>
		<?php if ( '' !== $atts['cta'] ) : ?>
		<div class="aav-testimonials__cta"><a class="aav-btn" href="<?php echo esc_url( $atts['url'] ); ?>"><?php echo esc_html( $atts['cta'] ); ?></a></div>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
} );

/* ================================================================== *
 * 9. Shortcode « Client Stories » (filtres Destination + Experience)
 * ================================================================== */
add_shortcode( 'aav_client_stories', function ( $atts ) {

	$atts = shortcode_atts( array(
		'eyebrow'     => '',
		'headline'    => '',
		'intro'       => '',
		'dest_label'  => 'Destination',
		'theme_label' => 'Experience',
		'all_label'   => 'All',
		'empty'       => 'No stories match your selection.',
		'layout'      => '',
	), $atts, 'aav_client_stories' );

	$layout = in_array( $atts['layout'], array( 'grid', 'editorial' ), true ) ? $atts['layout'] : aav_layout();

	$uid = 'aav-cs-' . substr( md5( uniqid( '', true ) ), 0, 8 );

	$q = new WP_Query( array( 'post_type' => 'aav_testimonial', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true ) );
	$destinations = get_terms( array( 'taxonomy' => 'aav_destination', 'hide_empty' => true ) );
	$experiences  = get_terms( array( 'taxonomy' => 'aav_occasion', 'hide_empty' => true ) );

	ob_start(); ?>
	<section id="<?php echo esc_attr( $uid ); ?>" class="aav-testimonials aav-testimonials--page">
		<?php echo aav_section_head( $atts['eyebrow'], $atts['headline'], $atts['intro'] ); ?>
		<div class="aav-filters" data-aav-filters>
			<?php if ( ! is_wp_error( $destinations ) && $destinations ) : ?>
			<div class="aav-filters__group" data-group="destination">
				<span class="aav-filters__label"><?php echo esc_html( $atts['dest_label'] ); ?></span>
				<button class="aav-chip is-active" data-value="all"><?php echo esc_html( $atts['all_label'] ); ?></button>
				<?php foreach ( $destinations as $t ) : ?><button class="aav-chip" data-value="<?php echo esc_attr( $t->slug ); ?>"><?php echo esc_html( $t->name ); ?></button><?php endforeach; ?>
			</div>
			<?php endif; ?>
			<?php if ( ! is_wp_error( $experiences ) && $experiences ) : ?>
			<div class="aav-filters__group" data-group="occasion">
				<span class="aav-filters__label"><?php echo esc_html( $atts['theme_label'] ); ?></span>
				<button class="aav-chip is-active" data-value="all"><?php echo esc_html( $atts['all_label'] ); ?></button>
				<?php foreach ( $experiences as $t ) : ?><button class="aav-chip" data-value="<?php echo esc_attr( $t->slug ); ?>"><?php echo esc_html( $t->name ); ?></button><?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>
		<div class="aav-grid aav-grid--3 aav-grid--<?php echo esc_attr( $layout ); ?>" data-aav-list>
			<?php while ( $q->have_posts() ) : $q->the_post(); echo aav_render_card( get_the_ID() ); endwhile; wp_reset_postdata(); ?>
		</div>
		<p class="aav-empty" data-aav-empty hidden><?php echo esc_html( $atts['empty'] ); ?></p>
	</section>
	<script>
	(function(){
		function init(){
			var root=document.getElementById(<?php echo wp_json_encode( $uid ); ?>); if(!root)return;
			var filters=root.querySelector("[data-aav-filters]"); if(!filters)return;
			var cards=root.querySelectorAll(".aav-card");
			var empty=root.querySelector("[data-aav-empty]");
			var state={destination:"all",occasion:"all"};
			filters.querySelectorAll(".aav-chip").forEach(function(btn){
				btn.addEventListener("click",function(){
					var grp=btn.closest("[data-group]");
					state[grp.getAttribute("data-group")]=btn.getAttribute("data-value");
					grp.querySelectorAll(".aav-chip").forEach(function(b){b.classList.remove("is-active");});
					btn.classList.add("is-active"); apply();
				});
			});
			function apply(){ var shown=0;
				cards.forEach(function(c){
					var d=(c.getAttribute("data-destination")||"").split(" ");
					var o=(c.getAttribute("data-occasion")||"").split(" ");
					var okD=state.destination==="all"||d.indexOf(state.destination)>-1;
					var okO=state.occasion==="all"||o.indexOf(state.occasion)>-1;
					var show=okD&&okO;
					c.style.display=show?"":"none"; if(show)shown++;
				});
				if(empty)empty.hidden=shown>0;
			}
		}
		if(document.readyState!=="loading"){init();}else{document.addEventListener("DOMContentLoaded",init);}
	})();
	</script>
	<?php
	return ob_get_clean();
} );

/* ================================================================== *
 * 10. Styles — palette bordeaux AAV (#6A171A)
 * ================================================================== */
add_action( 'wp_enqueue_scripts', function () {

	wp_register_style( 'aav-testimonials', false );
	wp_enqueue_style( 'aav-testimonials' );
	wp_add_inline_style( 'aav-testimonials', '
	.aav-testimonials{--aav-wine:#6A171A;--aav-wine-d:#4E1013;--aav-gold:#A8842C;--aav-ivory:#FBF8F4;--aav-ink:#20211E;--aav-muted:#8A8078;--aav-line:#E6DFD6;
		max-width:1200px;margin:0 auto;padding:88px 24px;color:var(--aav-ink);}
	.aav-testimonials__head{text-align:center;max-width:760px;margin:0 auto 56px;}
	.aav-testimonials__eyebrow{display:inline-block;font-size:11px;letter-spacing:.34em;text-transform:uppercase;color:var(--aav-wine);margin-bottom:18px;}
	/* Titre : Playfair Display, comme les autres titres du thème. */
	.aav-testimonials__title{font-family:"Playfair Display","Playfair",Georgia,"Times New Roman",serif;font-weight:400;color:var(--aav-wine);font-size:clamp(30px,3.6vw,44px);line-height:1.18;margin:0 0 18px;}
	.aav-testimonials__title em,.aav-testimonials__title i{font-style:italic;color:inherit;}
	.aav-testimonials__intro{color:var(--aav-muted);font-size:17px;line-height:1.75;margin:0;}
	.aav-testimonials__rule{width:52px;height:1px;background:var(--aav-wine);border:0;margin:26px auto 0;}

	/* Filtres */
	.aav-filters{display:flex;flex-wrap:wrap;gap:16px 36px;justify-content:center;align-items:baseline;margin:0 0 52px;}
	.aav-filters__group{display:flex;flex-wrap:wrap;align-items:center;gap:16px;}
	.aav-filters__label{font-size:10px;letter-spacing:.26em;text-transform:uppercase;color:var(--aav-muted);}
	.aav-chip{background:none;border:0;padding:4px 1px;font-size:13px;letter-spacing:.04em;color:var(--aav-ink);cursor:pointer;position:relative;font-family:inherit;transition:color .2s;}
	.aav-chip::after{content:"";position:absolute;left:0;right:0;bottom:-4px;height:1px;background:var(--aav-wine);transform:scaleX(0);transition:transform .24s ease;}
	.aav-chip:hover{color:var(--aav-wine);}
	.aav-chip.is-active{color:var(--aav-wine);}
	.aav-chip.is-active::after{transform:scaleX(1);}

	/* Grille & cartes */
	.aav-grid{display:grid;gap:28px;}
	.aav-grid--3{grid-template-columns:repeat(3,1fr);}
	@media(max-width:900px){.aav-grid--3{grid-template-columns:repeat(2,1fr);}}
	@media(max-width:620px){.aav-grid--3{grid-template-columns:1fr;}}

	.aav-card{position:relative;display:flex;flex-direction:column;background:#fff;border:1px solid var(--aav-line);overflow:hidden;transition:border-color .35s ease,box-shadow .35s ease,transform .35s ease;}
	.aav-card:hover{border-color:var(--aav-wine);box-shadow:0 22px 50px rgba(106,23,26,.13);transform:translateY(-5px);}
	.aav-card__media{position:relative;overflow:hidden;padding-top:62%;}
	.aav-card__media .aav-card__img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;transition:transform 1.1s cubic-bezier(.2,.7,.3,1);}
	.aav-card:hover .aav-card__media .aav-card__img{transform:scale(1.06);}
	.aav-card__media::after{content:"";position:absolute;inset:0;background:linear-gradient(0deg,rgba(78,16,19,.28),rgba(78,16,19,0) 55%);}
	.aav-card__body{position:relative;padding:38px 32px 32px;display:flex;flex-direction:column;gap:20px;flex:1 0 auto;}
	.aav-card__body::before{content:"\\201C";position:absolute;top:2px;left:20px;font-family:"Playfair Display",Georgia,serif;font-size:82px;line-height:1;color:var(--aav-wine);opacity:.11;pointer-events:none;}
	.aav-card__quote{position:relative;font-family:"Playfair Display","Playfair",Georgia,"Times New Roman",serif;font-size:17.5px;line-height:1.75;color:var(--aav-ink);margin:0;}
	.aav-card__foot{margin-top:auto;}
	.aav-card__foot::before{content:"";display:block;width:32px;height:1px;background:var(--aav-wine);margin-bottom:14px;}
	.aav-card__author{font-size:12px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--aav-wine);}
	.aav-card__meta{margin-top:5px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--aav-muted);}
	/* CTA : barre pleine largeur en pied de carte */
	.aav-card__cta{position:relative;display:flex;align-items:center;justify-content:space-between;gap:14px;
		margin-top:auto;padding:18px 32px;border-top:1px solid var(--aav-line);
		font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:var(--aav-wine);
		text-decoration:none;overflow:hidden;transition:color .35s ease,border-color .35s ease;}
	.aav-card__cta::before{content:"";position:absolute;inset:0;background:var(--aav-wine);
		transform:scaleX(0);transform-origin:left;transition:transform .45s cubic-bezier(.4,0,.2,1);z-index:0;}
	.aav-card__cta-label,.aav-card__cta-arrow{position:relative;z-index:1;}
	.aav-card__cta-arrow{font-size:14px;transition:transform .35s ease;}
	.aav-card:hover .aav-card__cta,.aav-card__cta:hover{color:#fff;border-color:var(--aav-wine);}
	.aav-card:hover .aav-card__cta::before,.aav-card__cta:hover::before{transform:scaleX(1);}
	.aav-card:hover .aav-card__cta-arrow{transform:translateX(5px);}

	/* CTA de section */
	.aav-testimonials__cta{text-align:center;margin-top:56px;}
	.aav-btn{display:inline-block;padding:15px 36px;border:1px solid var(--aav-wine);color:var(--aav-wine);text-decoration:none;font-size:11px;letter-spacing:.2em;text-transform:uppercase;transition:background .25s,color .25s;}
	.aav-btn:hover{background:var(--aav-wine);color:#fff;}
	.aav-empty{text-align:center;color:var(--aav-muted);padding:44px 0;font-style:italic;}

	/* ---------- Disposition ÉDITORIALE (magazine) ----------
	   Cycle de 4 : 3 cartes verticales, puis 1 carte pleine largeur
	   à l’horizontale (photo / texte), inversée une fois sur deux. */
	.aav-grid--editorial{grid-auto-flow:dense;}
	.aav-grid--editorial .aav-card:nth-child(4n){
		grid-column:1 / -1;
		display:grid;
		grid-template-columns:1fr 1fr;
		grid-template-rows:1fr auto;
		align-items:stretch;
	}
	.aav-grid--editorial .aav-card:nth-child(4n) .aav-card__media{
		grid-column:1;grid-row:1 / 3;padding-top:0;min-height:440px;height:100%;
	}
	.aav-grid--editorial .aav-card:nth-child(4n) .aav-card__body{
		grid-column:2;grid-row:1;justify-content:center;text-align:left;padding:56px 52px 36px;
	}
	.aav-grid--editorial .aav-card:nth-child(4n) .aav-card__body::before{font-size:104px;top:6px;left:34px;}
	.aav-grid--editorial .aav-card:nth-child(4n) .aav-card__quote{font-size:22px;line-height:1.68;}
	.aav-grid--editorial .aav-card:nth-child(4n) .aav-card__foot::before{margin-left:0;}
	.aav-grid--editorial .aav-card:nth-child(4n) .aav-card__cta{
		grid-column:2;grid-row:2;padding-left:52px;padding-right:52px;
	}
	/* une carte horizontale sur deux : photo à droite */
	.aav-grid--editorial .aav-card:nth-child(8n) .aav-card__media{grid-column:2;}
	.aav-grid--editorial .aav-card:nth-child(8n) .aav-card__body,
	.aav-grid--editorial .aav-card:nth-child(8n) .aav-card__cta{grid-column:1;}
	/* carte horizontale sans photo : pleine largeur, une seule colonne */
	.aav-grid--editorial .aav-card:nth-child(4n):not(.aav-card--photo){grid-template-columns:1fr;}
	.aav-grid--editorial .aav-card:nth-child(4n):not(.aav-card--photo) .aav-card__body,
	.aav-grid--editorial .aav-card:nth-child(4n):not(.aav-card--photo) .aav-card__cta{grid-column:1;}

	@media(max-width:900px){
		.aav-grid--editorial .aav-card:nth-child(4n){grid-template-columns:1fr;grid-template-rows:auto auto auto;}
		.aav-grid--editorial .aav-card:nth-child(4n) .aav-card__media,
		.aav-grid--editorial .aav-card:nth-child(8n) .aav-card__media{grid-column:1;grid-row:1;min-height:0;padding-top:62%;}
		.aav-grid--editorial .aav-card:nth-child(4n) .aav-card__body,
		.aav-grid--editorial .aav-card:nth-child(8n) .aav-card__body{grid-column:1;grid-row:2;padding:38px 32px 32px;}
		.aav-grid--editorial .aav-card:nth-child(4n) .aav-card__cta,
		.aav-grid--editorial .aav-card:nth-child(8n) .aav-card__cta{grid-column:1;grid-row:3;padding-left:32px;padding-right:32px;}
		.aav-grid--editorial .aav-card:nth-child(4n) .aav-card__quote{font-size:17.5px;}
	}
	' );
} );
