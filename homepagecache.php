<?php
/**
 * @copyright	Copyright (C) 2013 Omar Ramos. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// no direct access
defined('_JEXEC') or die;

/**
 * Homepage Cache Plugin
 *
 * @package		Joomla.Plugin
 * @subpackage	System.cache
 */
class plgSystemHomepageCache extends JPlugin
{

	var $_cache = null;

	/**
	 * Constructor
	 *
	 * @access	protected
	 * @param	object	$subject The object to observe
	 * @param	array	$config  An array that holds the plugin configuration
	 * @since	1.0
	 */
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);

		//Set the language in the class
		$config = JFactory::getConfig();
		$options = array(
			'defaultgroup'	=> 'page',
			'browsercache'	=> $this->params->get('browsercache', false),
			'caching'		=> false,
		);

		$this->_cache = JCache::getInstance('page', $options);
	}

	/**
	* Converting the site URL to fit to the HTTP request
	*
	*/
	public function onAfterInitialise()
	{
		global $_PROFILER;
		$app	= JFactory::getApplication();
		$user	= JFactory::getUser();

		if ($app->isAdmin()) {
			return;
		}

		$uri = JFactory::getURI()->toString();

		if ($this->isCacheable($uri))
		{
			if (count($app->getMessageQueue()))
			{
				return;
			}

			if ($user->get('guest') && $_SERVER['REQUEST_METHOD'] == 'GET')
			{
				$this->_cache->setCaching(true);
			}

			if ((int) $this->params->get('use_custom_cache_lifetime', 0))
			{
				$this->_cache->setLifeTime((int) $this->params->get('cache_lifetime', 15));
			}

            // Retrieve the Data from the Cache:
            $version = new JVersion;

            // Check the Joomla Version and Execute the appropriate code:
            if ($version->isCompatible('3.0'))
            {
                $data  = $this->_cache->get($this->createId());
            }
            else
            {
                $data  = $this->_cache->get();
            }

			if ($data !== false)
			{
				JResponse::setBody($data);

				if (JDEBUG)
				{
					$_PROFILER->mark('afterCache');
					$debugOutput =  implode('', $_PROFILER->getBuffer());
				}

				echo JResponse::toString($app->getCfg('gzip'));

				$app->close();
			}
		}
	}

	public function onAfterRender()
	{
		$app = JFactory::getApplication();

		if ($app->isAdmin()) {
			return;
		}

		$uri = JFactory::getURI()->toString();

		if ($this->isCacheable($uri))
		{
			if (count($app->getMessageQueue()))
			{
				return;
			}

			$user = JFactory::getUser();
			if ($user->get('guest'))
			{
				//We need to check again here, because auto-login plugins have not been fired before the first aid check
                $version = new JVersion;

                // Check the Joomla Version and Execute the appropriate code:
                if ($version->isCompatible('3.0'))
                {
				    $this->_cache->store(null, $this->createId());
                }
                else
                {
                    $this->_cache->store();
                }
			}
		}
	}

    protected function createId($uri = null)
    {
        if (empty($uri))
        {
            $uri = JFactory::getURI()->toString();
        }

        $uriSchemeHostPortPathParts = JFactory::getURI()->getScheme() . '://' . JFactory::getURI()->getHost() . JFactory::getURI()->getPort() . JFactory::getURI()->base(true);
        $relativeUriPathToCompare = str_replace($uriSchemeHostPortPathParts, '', $uri);


        if ($this->params->get('perbrowsercache', 0))
        {
            $id = md5($_SERVER['HTTP_USER_AGENT'] . $relativeUriPathToCompare);
        }
        else
        {
            $id = md5($relativeUriPathToCompare);
        }

        return $id;
    }

	protected function isCacheable($uri = '')
	{
		if (empty($uri))
		{
			$uri = JFactory::getURI()->toString();
		}

		$uriSchemeHostPortPathParts = JFactory::getURI()->getScheme() . '://' . JFactory::getURI()->getHost() . JFactory::getURI()->getPort() . JFactory::getURI()->base(true);
		$relativeUriPathToCompare = str_replace($uriSchemeHostPortPathParts, '', $uri);
        $relativeUriPathToCompare = str_replace('index.php/', '', $relativeUriPathToCompare);

		$allowedRelativeUriPaths = array();
		if ($this->params->get('homepagecache', 1))
		{
			// Add relative paths that could be used for the homepage:
			$allowedRelativeUriPaths[] = '';
			$allowedRelativeUriPaths[] = '/';
			$allowedRelativeUriPaths[] = 'index.php';
		}

		if ($this->params->get('priority_one_cache_switch', 1))
		{
			// Add switchable on/off Priority One Cache Relative URLs:
			$priorityOneCache = $this->params->get('priority_one_cache', '');
			$priorityOneCache = JString::trim($priorityOneCache);

			if (!empty($priorityOneCache))
			{
				$priorityOneUris = explode("\r\n", $priorityOneCache);
				foreach($priorityOneUris as $priorityOneUri)
				{
					$priorityOneUri = JString::trim($priorityOneUri);
                    $priorityOneUri = str_replace('index.php/', '', $priorityOneUri);
					$priorityOneUri = JString::ltrim($priorityOneUri, '/');
					$priorityOneUri = JString::rtrim($priorityOneUri, '/');
					$allowedRelativeUriPaths[] = $priorityOneUri;
				}
			}

		}

		if ($this->params->get('priority_two_cache_switch', 1))
		{
			// Add switchable on/off Priority Two Cache Relative URLs:
			$priorityTwoCache = $this->params->get('priority_two_cache', '');
			$priorityTwoCache = JString::trim($priorityTwoCache);

			if (!empty($priorityTwoCache))
			{
				$priorityTwoUris = explode("\r\n", $priorityTwoCache);
				foreach($priorityTwoUris as $priorityTwoUri)
				{
					$priorityTwoUri = JString::trim($priorityTwoUri);
                    $priorityTwoUri = str_replace('index.php/', '', $priorityTwoUri);
					$priorityTwoUri = JString::ltrim($priorityTwoUri, '/');
					$priorityTwoUri = JString::rtrim($priorityTwoUri, '/');
					$allowedRelativeUriPaths[] = $priorityTwoUri;
				}
			}
		}

		if ($this->params->get('priority_three_cache_switch', 1))
		{
			// Add switchable on/off Priority Three Cache Relative URLs:
			$priorityThreeCache = $this->params->get('priority_three_cache', '');
			$priorityThreeCache = JString::trim($priorityThreeCache);

			if (!empty($priorityThreeCache))
			{
				$priorityThreeUris = explode("\r\n", $priorityThreeCache);
				foreach($priorityThreeUris as $priorityThreeUri)
				{
					$priorityThreeUri = JString::trim($priorityThreeUri);
                    $priorityThreeUri = str_replace('index.php/', '', $priorityThreeUri);
					$priorityThreeUri = JString::ltrim($priorityThreeUri, '/');
					$priorityThreeUri = JString::rtrim($priorityThreeUri, '/');
					$allowedRelativeUriPaths[] = $priorityThreeUri;
				}
			}
		}

		$relativeUriPathToCompare = JString::ltrim($relativeUriPathToCompare, '/');
		$relativeUriPathToCompare = JString::rtrim($relativeUriPathToCompare, '/');
		$result = in_array($relativeUriPathToCompare, $allowedRelativeUriPaths);

        if (!$result)
        {
            if ($this->params->get('allow_wildcards', 0))
            {
                foreach($allowedRelativeUriPaths as $allowedRelativeUriPath)
                {
                    $wildcardResult = fnmatch($allowedRelativeUriPath, $relativeUriPathToCompare);

                    if ($wildcardResult)
                    {
                        $result = $wildcardResult;
                        break;
                    }
                }
            }

        }

		return $result;
	}
}
