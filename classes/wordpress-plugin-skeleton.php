<?php

if( $_SERVER[ 'SCRIPT_FILENAME' ] == __FILE__ )
	die( 'Access denied.' );

if( !class_exists( 'WordPressPluginSkeleton' ) )
{
	/**
	 * Main / front controller class
	 * WordPressPluginSkeleton is an object-oriented/MVC base for building WordPress plugins
	 * 
	 * @package WordPressPluginSkeleton
	 * @author Ian Dunn <ian@iandunn.name>
	 */
	class WordPressPluginSkeleton extends WPPSModule
	{
		public static $notices;									// Needs to be static so static methods can call enqueue notices. Needs to be public so other modules can enqueue notices.
		protected static $readableProperties	= array();		// These should really be constants, but PHP doesn't allow class constants to be arrays
		protected static $writeableProperties	= array();
		protected $modules;
		
		const VERSION		= '0.4a';
		const PREFIX		= 'wpps_';
		const DEBUG_MODE	= false;
		
		
		/*
		 * Magic methods
		 */
		
		/**
		 * Constructor
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		protected function __construct()
		{
			$this->registerHookCallbacks();
			
			$this->modules = array(
				'WPPSSettings'		=> WPPSSettings::getInstance(),
				'WPPSCPTExample'	=> WPPSCPTExample::getInstance(),
				'WPPSCron'			=> WPPSCron::getInstance()
			);
		}
		
		
		/*
		 * Static methods
		 */
		
		/**
		 * Enqueues CSS, JavaScript, etc
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public static function loadResources()
		{
			if( did_action( 'wp_enqueue_scripts' ) !== 1 && did_action( 'admin_enqueue_scripts' ) !== 1 )
				return;

			wp_register_script(
				self::PREFIX . 'wordpress-plugin-skeleton',
				plugins_url( 'javascript/wordpress-plugin-skeleton.js', dirname( __FILE__ ) ),
				array( 'jquery' ),
				self::VERSION,
				true
			);
			
			wp_register_style(
				self::PREFIX .'admin',
				plugins_url( 'css/admin.css', dirname( __FILE__ ) ),
				array(),
				self::VERSION,
				'all'
			);

			if( is_admin() )
				wp_enqueue_style( self::PREFIX . 'admin' );
			else
				wp_enqueue_script( self::PREFIX . 'wordpress-plugin-skeleton' );
		}
		
		/**
		 * Clears caches of content generated by caching plugins like WP Super Cache
		 * @mvc Model
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		protected static function clearCachingPlugins()
		{
			// WP Super Cache
			if( function_exists( 'wp_cache_clear_cache' ) )
				wp_cache_clear_cache();

			// W3 Total Cache
			if( class_exists( 'W3_Plugin_TotalCacheAdmin' ) )
			{
				$w3TotalCache =& w3_instance( 'W3_Plugin_TotalCacheAdmin' );

				if( method_exists( $w3TotalCache, 'flush_all' ) )
					$w3TotalCache->flush_all();
			}
		}


		/*
		 * Instance methods
		 */
		
		/**
		 * Prepares sites to use the plugin during single or network-wide activation
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param bool $networkWide
		 */
		public function activate( $networkWide )
		{
			global $wpdb;
			
			if( did_action( 'activate_' . plugin_basename( dirname( __DIR__ ) . '/bootstrap.php' ) ) !== 1 )
				return;

			if( function_exists( 'is_multisite' ) && is_multisite() )
			{
				if( $networkWide )
				{
					$blogs = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

					foreach( $blogs as $b )
					{
						switch_to_blog( $b );
						$this->singleActivate( $networkWide );
					}

					restore_current_blog();
				}
				else
					$this->singleActivate( $networkWide );
			}
			else
				$this->singleActivate( $networkWide );
		}

		/**
		 * Runs activation code on a new WPMS site when it's created
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param int $blogID
		 */
		public function activateNewSite( $blogID )
		{
			if( did_action( 'wpmu_new_blog' ) !== 1 )
				return;

			switch_to_blog( $blogID );
			$this->singleActivate( $networkWide );
			restore_current_blog();
		}

		/**
		 * Prepares a single blog to use the plugin
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param bool $networkWide
		 */
		protected function singleActivate( $networkWide )
		{
			foreach( $this->modules as $module )
				$module->activate( $networkWide );
			
			flush_rewrite_rules();
		}

		/**
		 * Rolls back activation procedures when de-activating the plugin
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function deactivate()
		{
			foreach( $this->modules as $module )
				$module->deactivate();
			
			flush_rewrite_rules();
		}
		 
		/**
		 * Register callbacks for actions and filters
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function registerHookCallbacks()
		{
			// NOTE: Make sure you update the did_action() parameter in the corresponding callback method when changing the hooks here
			add_action( 'wpmu_new_blog', 			__CLASS__ . '::activateNewSite' );
			add_action( 'wp_enqueue_scripts',		__CLASS__ . '::loadResources' );
			add_action( 'admin_enqueue_scripts',	__CLASS__ . '::loadResources' );
			
			add_action( 'init',						array( $this, 'init' ) );
			add_action( 'init',						array( $this, 'upgrade' ), 11 );
		}
		
		/**
		 * Initializes variables
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 */
		public function init()
		{
			if( did_action( 'init' ) !== 1 )
				return;

			self::$notices = IDAdminNotices::getSingleton();
			if( self::DEBUG_MODE )
				self::$notices->debugMode = true;
			
			try
			{
				$instanceExample = new WPPSInstanceClass( 'Instance example', '42' );
				//self::$notices->enqueue( $instanceExample->foo .' '. $instanceExample->bar );
			}
			catch( Exception $e )
			{
				self::$notices->enqueue( __METHOD__ . ' error: '. $e->getMessage(), 'error' );
			}
		}

		/**
		 * Checks if the plugin was recently updated and upgrades if necessary
		 * @mvc Controller
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param string $dbVersion
		 */
		public function upgrade( $dbVersion = 0 )
		{
			if( did_action( 'init' ) !== 1 )
				return;
			
			if( version_compare( $this->modules[ 'WPPSSettings' ]->settings[ 'db-version' ], self::VERSION, '==' ) )
				return;
			
			foreach( $this->modules as $module )
				$module->upgrade( $this->modules[ 'WPPSSettings' ]->settings[ 'db-version' ] );
			
			$this->modules[ 'WPPSSettings' ]->settings = array( 'db-version' => self::VERSION );
			self::clearCachingPlugins();
		}
		
		/**
		 * Checks that the object is in a correct state
		 * @mvc Model
		 * @author Ian Dunn <ian@iandunn.name>
		 * @param string $property An individual property to check, or 'all' to check all of them
		 * @return bool
		 */
		protected function isValid( $property = 'all' )
		{
			return true;
		}
	} // end WordPressPluginSkeleton
	
	require_once( dirname( __DIR__  ) . '/includes/IDAdminNotices/id-admin-notices.php' );
	require_once( dirname( __FILE__ ) . '/wpps-custom-post-type.php' );
	require_once( dirname( __FILE__ ) . '/wpps-cpt-example.php' );
	require_once( dirname( __FILE__ ) . '/wpps-settings.php' );
	require_once( dirname( __FILE__ ) . '/wpps-cron.php' );
	require_once( dirname( __FILE__ ) . '/wpps-instance-class.php' );
}

?>