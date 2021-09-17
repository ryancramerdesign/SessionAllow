<?php namespace ProcessWire;

/**
 * Session Allow Module
 * 
 * Enables you to configure whether to allow session for each request based on configured rules.
 * 
 * Copyright 2021 by Ryan Cramer | MPL 2.0
 * 
 * @method bool allowSession(Session $session)
 * @property bool $defaultYes
 * @property bool $loginYes
 * @property array $yesRules
 * @property array $noRules
 * @property array $yesHosts
 * @property array $noHosts
 * 
 */
class SessionAllow extends WireData implements Module, ConfigurableModule {
	
	public static function getModuleInfo() {
		return [
			'title' => 'Session Allow',
			'summary' => 'Enables admin configuration of where sessions are allowed and/or disallowed.',
			'icon' => '',
			'version' => 1,
			'author' => 'Ryan Cramer',
			'requires' => 'ProcessWire>=3.0.184',
			'autoload' => true,
			'singular' => true,
		];
	}

	/**
	 * Contains existing callable method when defined in $config->sessionAllow
	 * 
	 * @var bool|callable
	 * 
	 */
	protected $existingCallable = false;

	/**
	 * Construct
	 * 
	 */
	public function __construct() {
		parent::__construct();
		parent::setArray([
			'defaultYes' => true,
			'loginYes' => true,
			'yesRules' => [],
			'noRules' => [],
			'yesHosts' => [],
			'noHosts' => [], 
			'yesCookies' => [],
		]);
	}

	/**
	 * Wired to API
	 * 
	 */
	public function wired() {

		$module = $this;
		$config = $this->wire()->config;
		$modules = $this->wire()->modules;
		
		$sessionAllow = $config->sessionAllow;
		$this->yesHosts = $config->httpHosts;
		$this->setArray($modules->getConfig($this));
		
		if($sessionAllow !== null && !is_bool($sessionAllow)) {
			if(is_callable($sessionAllow)) {
				$this->existingCallable = $sessionAllow;
			}
		}

		$config->sessionAllow = function($session) use($module) {
			return $module->allowSession($session);
		};
	}
	
	/**
	 * Init
	 *
	 */
	public function init() {
		// not yet used
	}

	/**
	 * Allow session?
	 * 
	 * @param Session $session
	 * @return bool
	 * 
	 */
	public function ___allowSession($session) {
		
		$config = $this->wire()->config;
		$pages = $this->wire()->pages;
		$allow = $this->defaultYes;
		
		if($this->existingCallable) {
			$allow = call_user_func_array($this->existingCallable, array($session));
			if($allow === false) return false;
		}

		$path = $this->requestPath();
		if($path === '') return true;
	
		if($this->loginYes && $session->hasLoginCookie()) {
			return true;
		}
		
		if($this->defaultYes) {
			$noRules = $this->noRules;
			$noHosts = $this->noHosts;
			if(count($noHosts) && $this->hostMatches($config->httpHost, $noHosts)) {
				$allow = false;
			} else if(count($noRules)) {
				$allow = $this->pathMatchesRules($path, $noRules) === false;
			}
		} else {
			$yesRules = $this->yesRules;
			$yesHosts = $this->yesHosts;
			if(count($yesHosts)) $allow = $this->hostMatches($config->httpHost, $yesHosts);
			if($allow !== false && count($yesRules)) {
				$allow = $this->pathMatchesRules($path, $yesRules) !== false;
			}
		}
		
		if(!$allow) {
			// admin URLs always allowed
			$adminPath = $pages->getPath($config->adminRootPageID);
			if($adminPath && strpos($path, $adminPath) === 0) $allow = true;
		}
		
		if(!$allow) {
			// files path always allowed if pagefileSecure in use
			$rootUrl = $config->urls->root;
			$filesPath = substr($config->urls->files, strlen($rootUrl)-1); 
			if(strpos($path, $filesPath) === 0) $allow = $config->pagefileSecure;
		}
		
		return $allow;
	}

	/**
	 * Get current request path
	 * 
	 * @return string Returns path or blank string if not a web request
	 * 
	 */
	protected function requestPath() {
		
		$config = $this->wire()->config;
		$rootUrl = $config->urls->root; 
		
		if(isset($_GET['it'])) {
			$path = $_GET['it'];
		} else if(isset($_SERVER['REQUEST_URI'])) {
			$path = substr($_SERVER['REQUEST_URI'], strlen($rootUrl)-1);
		} else {
			return '';
		}
		
		$path = '/' . ltrim($path, '/');
		
		if(strpos($path, '?') !== false) {
			list($path, /*$queryString*/) = explode('?', $path, 2);
		}

		return $path;
	}

	/**
	 * Does given path match any of given rules?
	 * 
	 * @param string $path
	 * @param array $rules
	 * @return bool|string Returns matching rule when yes, boolean false when no
	 * 
	 */
	protected function pathMatchesRules($path, array $rules) {
		
		$delims = array('#', '%', '@');
		$wildcards = array('*', '+', '?', '(');
		$match = false;
		
		foreach($rules as $line) {
			$line = trim($line);
			if(empty($line)) continue;
			$line = str_replace($delims, '!', $line);
			$wildcard = str_replace($wildcards, '', $line) !== $line;
			$regex = '';
			if(strpos($line, '!') === 0) {
				$regex = $line; // specific regex
			} else if($wildcard) {
				$line = str_replace(array('*', '+'), array('.*', '.+'), $line);
				$regex = '!^' . $line . '$!'; // wildcards regex
			}
			if($regex) {
				$match = preg_match($regex, $path);
			} else {
				$match = $path === $line; // exact match
			}
			if($match) {
				$match = $line;
				break;
			} else {
				$match = false;
			}
		}
		
		return $match;
	}

	/**
	 * Does current http host match any of those given?
	 * 
	 * @param array $hosts
	 * @param string $httpHost
	 * @return bool
	 * 
	 */
	protected function hostMatches($httpHost, array $hosts) {
		$httpHost = strtolower(trim($httpHost));
		$matches = false;
		foreach($hosts as $host) {
			$matches = strtolower($host) === $httpHost;
			if($matches) break;
		}
		return $matches;
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @return WireData
	 * 
	 */
	public function set($key, $value) {
		$v = parent::get($key);	
		if($key === 'defaultYes' || $key === 'loginYes') {
			if(!is_bool($value)) $value = (bool) ((int) $value); 
		} else if($v !== null && is_array($v) && is_string($value)) {
			$value = explode("\n", $value);
			foreach($value as $k => $v) {
				$value[$k] = trim($v);
				if(!strlen($value[$k])) unset($value[$k]); 	
			}
		}
		return parent::set($key, $value);
	}

	/**
	 * @return string
	 * 
	 */
	protected function checkTests() {
		
		$input = $this->wire()->input;
		$session = $this->wire()->session;

		if($input->post('_tests')) {
			$tests = $input->post->textarea('_tests');
			$session->setFor($this, 'tests', $tests);
			return $tests;
		}

		$tests = $session->getFor($this, 'tests');
		$session->removeFor($this, 'tests');
		
		if(empty($tests)) return '';
		
		$testPathRules = $this->defaultYes ? $this->noRules : $this->yesRules;
		$testHostRules = $this->defaultYes ? $this->noHosts : $this->yesHosts;
		
		foreach(explode("\n", $tests) as $test) {
			$test = trim($test);
			if(empty($test)) continue;
			if(strpos($test, '/') === 0) {
				$matchRule = $this->pathMatchesRules($test, $testPathRules);
				if($matchRule !== false) {
					$message = "PATH $test MATCHED by rule: $matchRule";
				} else {
					$message = "PATH $test NOT MATCHED";
				}
			} else {
				if($this->hostMatches($test, $testHostRules)) {
					$message = "HOST $test MATCHED";
				} else {
					$message = "HOST $test NOT MATCHED";
				}
			}
			if($message) $this->message($message, Notice::noGroup);
		}
		
		return $tests;
	}

	/**
	 * Module config
	 * 
	 * @param InputfieldWrapper $inputfields
	 * 
	 */
	public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {
	
		$config = $this->wire()->config;
		
		if($this->existingCallable) {
			$this->warning(
				'There is already a sessionAllow handler defined. ' . 
				'If you want this module to have full control over whether sessions are allowed, ' . 
				'remove the existing $config->sessionAllow in /site/config.php.',
				Notice::noGroup
			);
		}
		
		$wildcardNote = $this->_('Optionally use wildcards, i.e. `/foo/bar/\*`, `\*/foo/bar/`, `\*/foo/\*`, etc.') . ' ';
		$regexNote = $this->_('To use a PCRE regular expression, use `!` as delimiter, i.e. `!^/foo/(bar|baz)/?$!`.') . ' '; 
		$subdirNote = $this->_('Rules should exclude any subdirectory that the site runs from.'); 
		
		$f = $inputfields->InputfieldToggle; 
		$f->attr('name', 'defaultYes');
		$f->label = $this->_('Allow sessions by default?'); 
		$f->val((int) $this->defaultYes);
		$f->columnWidth = 50;
		$inputfields->add($f);
		
		$f = $inputfields->InputfieldToggle;
		$f->attr('name', 'loginYes');
		$f->label = $this->_('Session always active when login cookie present?');
		$f->val((int) $this->loginYes);
		$f->columnWidth = 50;
		$inputfields->add($f);
		
		$f = $inputfields->InputfieldTextarea;
		$f->attr('name', 'noRules');
		$f->label = $this->_('Disallow session when request URL matches'); 
		$f->description = 
			$this->_('Enter one per line of URLs where sessions are disabled, for example `/foo/bar/`.') . ' ' . 
			$wildcardNote . $subdirNote;
		$f->notes = $regexNote;
		$f->showIf = 'defaultYes=1';
		$f->val(implode("\n", $this->noRules));
		$inputfields->add($f);

		$f = $inputfields->InputfieldTextarea;
		$f->attr('name', 'yesRules');
		$f->label = $this->_('Allow session when request URL matches');
		$f->description =
			$this->_('Enter one per line of URLs where sessions are allowed, for example `/foo/bar/`.') . ' ' .
			$wildcardNote . $subdirNote;
		$f->notes = $regexNote . ' ' . $this->_('Note that the admin URL is always allowed.');
		$f->showIf = 'defaultYes=0';
		$f->val(implode("\n", $this->yesRules));
		$inputfields->add($f);
		
		$f = $inputfields->InputfieldCheckboxes;
		$f->attr('name', 'noHosts');
		$f->label = $this->_('Disallow sessions for checked hosts'); 
		foreach($config->httpHosts as $host) {
			$f->addOption($host);
		}
		$f->val($this->noHosts);
		$f->showIf = 'defaultYes=1';
		$inputfields->add($f);
		
		$f = $inputfields->InputfieldCheckboxes;
		$f->attr('name', 'yesHosts');
		$f->label = $this->_('Allow sessions for checked hosts');
		foreach($config->httpHosts as $host) {
			$f->addOption($host);
		}
		$f->val($this->yesHosts);
		$f->showIf = 'defaultYes=0';
		$inputfields->add($f);
	
		$f = $inputfields->InputfieldTextarea;
		$f->attr('name', '_tests');
		$f->label = $this->_('Test rules');
		$f->description = $this->_('Enter one or more page paths or host names (one per line) to test your rules above.');
		$f->val($this->checkTests());
		$f->collapsed = Inputfield::collapsedBlank;
		$inputfields->add($f);
	}
}