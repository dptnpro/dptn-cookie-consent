<?php
/**
 * Страница настроек: Настройки → Cookie-согласие.
 *
 * Нативный Settings API, без сборки. Все вкладки пишут в одну опцию,
 * поэтому sanitize() обновляет только поля отправленной вкладки.
 */

namespace DPTN\CookieConsent;

defined( 'ABSPATH' ) || exit;

final class Admin {

	const PAGE  = 'dptn-cc';
	const GROUP = 'dptn_cc';

	const TABS = array(
		'general'    => 'Основное',
		'texts'      => 'Тексты',
		'services'   => 'Сервисы',
		'appearance' => 'Внешний вид',
		'log'        => 'Журнал',
		'help'       => 'Справка',
	);

	const POSITIONS = array(
		'bottom'       => 'Внизу по центру',
		'bottom-left'  => 'Внизу слева',
		'bottom-right' => 'Внизу справа',
		'center'       => 'По центру с затемнением',
	);

	/** @var string */
	private static $hook = '';

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'register' ) );
		add_action( 'admin_init', array( Log::class, 'maybe_install' ) );
		add_action( 'admin_post_dptn_cc_export', array( self::class, 'export' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DPTN_CC_FILE ), array( self::class, 'action_links' ) );
	}

	public static function menu(): void {
		self::$hook = (string) add_options_page(
			'Cookie-согласие',
			'Cookie-согласие',
			'manage_options',
			self::PAGE,
			array( self::class, 'render' )
		);
	}

	public static function register(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Ассеты только на своей странице.
	 */
	public static function assets( string $hook ): void {
		if ( $hook !== self::$hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'dptn-cc', DPTN_CC_URL . dptn_cc_asset_path( 'css' ), array(), DPTN_CC_VERSION );
		wp_enqueue_style( 'dptn-cc-admin', DPTN_CC_URL . 'admin/admin.css', array( 'dptn-cc' ), DPTN_CC_VERSION );
		wp_enqueue_script( 'dptn-cc-admin', DPTN_CC_URL . 'admin/admin.js', array( 'jquery', 'wp-color-picker' ), DPTN_CC_VERSION, true );
	}

	/**
	 * @param string[] $links
	 * @return string[]
	 */
	public static function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">Настройки</a>' );

		return $links;
	}

	public static function url( string $tab = 'general' ): string {
		return add_query_arg(
			array(
				'page' => self::PAGE,
				'tab'  => $tab,
			),
			admin_url( 'options-general.php' )
		);
	}

	/**
	 * Имя поля формы: name( 'texts', 'title' ) → dptn_cc_settings[texts][title].
	 */
	public static function name( string ...$keys ): string {
		return Settings::OPTION . '[' . implode( '][', $keys ) . ']';
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- только выбор вкладки.
		if ( ! isset( self::TABS[ $tab ] ) ) {
			$tab = 'general';
		}

		$s = Settings::stored();
		?>
		<div class="wrap dptn-cc-admin">
			<h1>Cookie-согласие</h1>

			<?php if ( has_filter( 'dptn_cc_settings' ) ) : ?>
				<div class="notice notice-info inline"><p>
					Часть настроек может переопределяться кодом темы или плагина через фильтр <code>dptn_cc_settings</code>.
					Значения из кода важнее сохранённых здесь.
				</p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper">
				<?php foreach ( self::TABS as $id => $label ) : ?>
					<a href="<?php echo esc_url( self::url( $id ) ); ?>" class="nav-tab<?php echo $id === $tab ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php if ( 'help' === $tab ) : ?>
				<?php include DPTN_CC_DIR . 'admin/views/help.php'; ?>
			<?php else : ?>
				<form method="post" action="options.php">
					<?php settings_fields( self::GROUP ); ?>
					<input type="hidden" name="<?php echo esc_attr( self::name( '_tab' ) ); ?>" value="<?php echo esc_attr( $tab ); ?>">
					<?php include DPTN_CC_DIR . "admin/views/$tab.php"; ?>
					<?php submit_button(); ?>
				</form>
				<?php
				// Части вкладки вне формы настроек, например список журнала со своими фильтрами.
				if ( is_readable( DPTN_CC_DIR . "admin/views/$tab-after.php" ) ) {
					include DPTN_CC_DIR . "admin/views/$tab-after.php";
				}
				?>
			<?php endif; ?>

			<p class="dptn-cc-admin__credits">
				DPTN Cookie Consent <?php echo esc_html( DPTN_CC_VERSION ); ?> · <a href="https://dptn.pro" target="_blank" rel="noopener">dptn.pro</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Валидация: берём сохранённое и обновляем только поля отправленной вкладки.
	 *
	 * @param mixed $input
	 */
	public static function sanitize( $input ): array {
		$old = get_option( Settings::OPTION, array() );
		$old = is_array( $old ) ? $old : array();

		// При первом сохранении WordPress вызывает фильтр второй раз — уже с готовым массивом из add_option().
		if ( ! is_array( $input ) || ! isset( $input['_tab'] ) ) {
			return is_array( $input ) ? $input : $old;
		}

		$d    = Settings::defaults();
		$out  = $old;
		$bump = false;

		switch ( $input['_tab'] ) {
			case 'general':
				$out['mode']        = 'notice' === ( $input['mode'] ?? '' ) ? 'notice' : 'strict';
				$out['cookie_days'] = min( 730, max( 1, absint( $input['cookie_days'] ?? $d['cookie_days'] ) ) );
				foreach ( array( 'consent', 'policy' ) as $key ) {
					$page                  = absint( $input[ $key . '_page' ] ?? 0 );
					$url                   = $page ? get_permalink( $page ) : '';
					$out[ $key . '_page' ] = $page;
					$out[ $key . '_url' ]  = $url ? (string) $url : '';
				}
				$bump = ! empty( $input['bump_version'] );
				break;

			case 'texts':
				foreach ( $d['texts'] as $key => $default ) {
					$value                = (string) ( $input['texts'][ $key ] ?? '' );
					$value                = 'body' === $key ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
					$out['texts'][ $key ] = '' !== $value ? $value : $default;
				}
				foreach ( $d['categories'] as $id => $category ) {
					$label = sanitize_text_field( (string) ( $input['categories'][ $id ]['label'] ?? '' ) );
					$desc  = sanitize_textarea_field( (string) ( $input['categories'][ $id ]['desc'] ?? '' ) );

					$out['categories'][ $id ] = array(
						'label' => '' !== $label ? $label : $category['label'],
						'desc'  => '' !== $desc ? $desc : $category['desc'],
					);
				}
				break;

			case 'services':
				$metrika = (array) ( $input['services']['yandex_metrika'] ?? array() );

				$out['services']['yandex_metrika'] = array(
					'enabled'    => ! empty( $metrika['enabled'] ),
					'counter_id' => preg_replace( '/\D/', '', (string) ( $metrika['counter_id'] ?? '' ) ),
					'webvisor'   => ! empty( $metrika['webvisor'] ),
					'clickmap'   => ! empty( $metrika['clickmap'] ),
					'ecommerce'  => ! empty( $metrika['ecommerce'] ),
				);

				$out['scripts'] = self::sanitize_scripts( (array) ( $input['scripts'] ?? array() ) );

				foreach ( $d['embeds'] as $id => $off ) {
					$out['embeds'][ $id ] = ! empty( $input['embeds'][ $id ] );
				}
				break;

			case 'log':
				$out['log'] = array(
					'enabled'   => ! empty( $input['log']['enabled'] ),
					'ip'        => 'truncated' === ( $input['log']['ip'] ?? '' ) ? 'truncated' : 'none',
					'retention' => min( 3650, max( 30, absint( $input['log']['retention'] ?? $d['log']['retention'] ) ) ),
					'daily_cap' => min( 1000000, max( 100, absint( $input['log']['daily_cap'] ?? $d['log']['daily_cap'] ) ) ),
				);
				break;

			case 'appearance':
				$out['position']      = isset( self::POSITIONS[ $input['position'] ?? '' ] ) ? $input['position'] : $d['position'];
				$out['inline_assets'] = ! empty( $input['inline_assets'] );
				$out['floating']      = in_array( $input['floating'] ?? '', array( 'left', 'right' ), true ) ? $input['floating'] : 'none';
				foreach ( array( 'accent', 'accent_fg', 'bg', 'fg' ) as $key ) {
					$color                = sanitize_hex_color( (string) ( $input['style'][ $key ] ?? '' ) );
					$out['style'][ $key ] = $color ? strtolower( $color ) : $d['style'][ $key ];
				}
				$out['style']['radius'] = min( 32, absint( $input['style']['radius'] ?? $d['style']['radius'] ) );
				break;
		}

		// Согласие давалось на другой список сервисов — спрашиваем посетителей заново.
		if ( self::active_services( $old ) !== self::active_services( $out ) ) {
			$bump = true;
		}

		if ( $bump ) {
			$out['version'] = max( 1, (int) ( $old['version'] ?? $d['version'] ) ) + 1;
			self::record_version( $out );
			add_settings_error(
				Settings::OPTION,
				'dptn_cc_version',
				sprintf( 'Версия согласия повышена до %d: баннер покажется всем посетителям заново.', $out['version'] ),
				'info'
			);
		}

		return $out;
	}

	/**
	 * Названия включённых сервисов, как их видит посетитель в баннере.
	 *
	 * @return string[]
	 */
	private static function service_names( array $settings ): array {
		$names   = array();
		$catalog = Services::catalog();
		foreach ( (array) ( $settings['services'] ?? array() ) as $id => $options ) {
			if ( ! empty( $options['enabled'] ) && ( ! array_key_exists( 'counter_id', $options ) || '' !== $options['counter_id'] ) ) {
				$names[] = (string) ( $catalog[ $id ]['name'] ?? $id );
			}
		}
		foreach ( (array) ( $settings['scripts'] ?? array() ) as $row ) {
			$names[] = (string) $row['name'];
		}
		foreach ( array_filter( (array) ( $settings['embeds'] ?? array() ) ) as $id => $on ) {
			$names[] = Services::EMBEDS[ $id ][0] ?? $id;
		}

		return $names;
	}

	/**
	 * Выгрузка журнала в CSV с текущими фильтрами.
	 */
	public static function export(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'dptn_cc_export' ) ) {
			wp_die( 'Недостаточно прав.', 403 );
		}

		Log::export( Log::filters( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- проверено выше.
		exit;
	}

	/**
	 * Реестр версий согласия: на какой текст и список сервисов соглашался посетитель.
	 * Хеш текста позволяет доказать, что страница согласия с тех пор не менялась.
	 */
	private static function record_version( array $settings ): void {
		$versions = get_option( 'dptn_cc_versions', array() );
		$versions = is_array( $versions ) ? $versions : array();
		$page     = (int) ( $settings['consent_page'] ?? 0 );
		$content  = $page ? (string) get_post_field( 'post_content', $page ) : '';

		$versions[ (int) $settings['version'] ] = array(
			'date'     => gmdate( 'Y-m-d H:i:s' ),
			'url'      => (string) ( $settings['consent_url'] ?? '' ),
			'hash'     => '' !== $content ? hash( 'sha256', $content ) : '',
			'services' => self::service_names( $settings ),
		);

		update_option( 'dptn_cc_versions', $versions, false );
	}

	/**
	 * Скрипты, подключённые на главной: запрос к своему сайту по нажатию кнопки, не в фоне.
	 * Показываем внешние скрипты и известные по подсказкам — локальные служебные не интересны.
	 *
	 * @return array[]|\WP_Error handle, src, host, hint.
	 */
	public static function scan() {
		$response = wp_remote_get(
			home_url( '/' ),
			array(
				'timeout'   => 15,
				'sslverify' => false, // Свой сайт; на локальных стендах сертификат самоподписанный.
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		preg_match_all( '/<script\b[^>]*\bid=["\']([^"\']+)-js["\'][^>]*>/i', wp_remote_retrieve_body( $response ), $tags, PREG_SET_ORDER );

		$site  = wp_parse_url( home_url(), PHP_URL_HOST );
		$found = array();
		foreach ( $tags as $tag ) {
			$src  = preg_match( '/\ssrc=["\']([^"\']+)["\']/i', $tag[0], $m ) ? html_entity_decode( $m[1], ENT_QUOTES ) : '';
			$host = $src ? (string) wp_parse_url( $src, PHP_URL_HOST ) : '';
			$hint = Services::hint( $tag[1], $src );
			if ( ! $hint && ( '' === $host || $host === $site ) ) {
				continue;
			}
			$found[ $tag[1] ] = array(
				'handle' => $tag[1],
				'src'    => $src,
				'host'   => $host,
				'hint'   => $hint,
			);
		}

		return array_values( $found );
	}

	/**
	 * Строки таблицы «Скрипты темы и плагинов»: пустые и отмеченные к удалению отбрасываются,
	 * повторяющиеся handle схлопываются.
	 */
	private static function sanitize_scripts( array $rows ): array {
		$clean = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! empty( $row['delete'] ) ) {
				continue;
			}
			$handle   = preg_replace( '/[^A-Za-z0-9_.\-]/', '', (string) ( $row['handle'] ?? '' ) );
			$category = (string) ( $row['category'] ?? '' );
			// Свой скрипт баннера (handle dptn-cc, см. Frontend::HANDLE) блокировать нельзя — иначе согласие некому будет спросить.
			if ( '' === $handle || 'dptn-cc' === $handle || isset( $clean[ $handle ] ) || ! in_array( $category, array( 'analytics', 'marketing', 'functional' ), true ) ) {
				continue;
			}
			$name    = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
			$cookies = array_filter(
				array_map(
					static function ( $prefix ) {
						return preg_replace( '/[^A-Za-z0-9_.\-]/', '', $prefix );
					},
					explode( ',', (string) ( $row['cookies'] ?? '' ) )
				)
			);

			// CSS-селектор блока, где до согласия показать заглушку (например, контейнер карты).
			$placeholder = trim( substr( preg_replace( '/[^A-Za-z0-9_\-.#\[\]="\':\s>~+*,()^$|]/', '', (string) ( $row['placeholder'] ?? '' ) ), 0, 200 ) );

			$clean[ $handle ] = array(
				'handle'      => $handle,
				'category'    => $category,
				'name'        => '' !== $name ? $name : $handle,
				'cookies'     => array_values( $cookies ),
				'placeholder' => $placeholder,
			);
		}

		return array_values( $clean );
	}

	/**
	 * Включённые сервисы с заполненными обязательными параметрами, скрипты и встроенный контент.
	 *
	 * @return string[]
	 */
	private static function active_services( array $settings ): array {
		$active = array();
		foreach ( (array) ( $settings['services'] ?? array() ) as $id => $options ) {
			if ( ! empty( $options['enabled'] ) && ( ! array_key_exists( 'counter_id', $options ) || '' !== $options['counter_id'] ) ) {
				$active[] = (string) $id;
			}
		}
		foreach ( (array) ( $settings['scripts'] ?? array() ) as $row ) {
			$active[] = 'script:' . $row['handle'] . ':' . $row['category'];
		}
		foreach ( array_filter( (array) ( $settings['embeds'] ?? array() ) ) as $id => $on ) {
			$active[] = 'embed:' . $id;
		}
		sort( $active );

		return $active;
	}
}
