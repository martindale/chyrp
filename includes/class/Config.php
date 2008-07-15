<?php
	if (!defined("INCLUDES_DIR")) define("INCLUDES_DIR", dirname(__FILE__));

	/**
	 * Class: Config
	 * Holds all of the configuration variables for the entire site, as well as Module settings.
	 */
	class Config {
		# Variable: $yaml
		# Holds all of the YAML settings as a $key => $val array.
		private $yaml = array();

		# Variable: $file
		# The current file loaded.
		private $file = null;

		/**
		 * The class constructor is private so there is only one instance and config is guaranteed to be kept in sync.
		 */
		private function __construct() {}

		/**
		 * Function: load
		 * Loads a given configuration YAML file.
		 *
		 * Parameters:
		 *     $file - The YAML file to load into <Config>.
		 */
		public function load($file) {
			if (!file_exists($file))
				return false;

			$this->file = $file;

			$contents = str_replace("<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>\n",
			                        "",
			                        file_get_contents($file));

			$this->yaml = Horde_Yaml::load($contents);

			$arrays = array("enabled_modules", "enabled_feathers", "routes");
			foreach ($this->yaml as $setting => $value)
				if (in_array($setting, $arrays) and empty($value))
					$this->$setting = array();
				elseif (!is_int($setting)) # Don't load the "---"
					$this->$setting = (is_string($value)) ? stripslashes($value) : $value ;

			fallback($this->url, $this->chyrp_url);
		}

		/**
		 * Function: set
		 * Sets a variable's value.
		 *
		 * Parameters:
		 *     $setting - The setting name.
		 *     $value - The new value. Can be boolean, numeric, an array, a string, etc.
		 */
		public function set($setting, $value, $overwrite = true) {
			if (isset($this->$setting) and $this->$setting == $value and !$overwrite)
				return false;

			if (isset($this->file) and file_exists($this->file)) {
				$contents = str_replace("<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>\n",
				                        "",
				                        file_get_contents($this->file));

				$this->yaml = Horde_Yaml::load($contents);
			}

			# Add the setting
			$this->yaml[$setting] = $this->$setting = $value;

			if (class_exists("Trigger"))
				Trigger::current()->call("change_setting", $setting, $value, $overwrite);

			# Add the PHP protection!
			$contents = "<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>\n";

			# Generate the new YAML settings
			$contents.= Horde_Yaml::dump($this->yaml);

			if (!@file_put_contents(INCLUDES_DIR."/config.yaml.php", $contents)) {
				Flash::warning(_f("Could not set \"<code>%s</code>\" configuration setting because <code>%s</code> is not writable.", array($setting, "/includes/config.yaml.php")));
				return false;
			} else
				return true;
		}

		/**
		 * Function: remove
		 * Removes a configuration setting.
		 *
		 * Parameters:
		 *     $setting - The name of the variable to remove.
		 */
		public function remove($setting) {
			if (isset($this->file) and file_exists($this->file)) {
				$contents = str_replace("<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>\n",
				                        "",
				                        file_get_contents($this->file));

				$this->yaml = Horde_Yaml::load($contents);
			}

			# Add the setting
			unset($this->yaml[$setting]);

			# Add the PHP protection!
			$contents = "<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>\n";

			# Generate the new YAML settings
			$contents.= Horde_Yaml::dump($this->yaml);

			file_put_contents(INCLUDES_DIR."/config.yaml.php", $contents);
		}

		/**
		 * Function: current
		 * Returns a singleton reference to the current configuration.
		 */
		public static function & current() {
			static $instance = null;
			return $instance = (empty($instance)) ? new self() : $instance ;
		}
	}
	$config = Config::current();
