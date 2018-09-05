<?php
namespace SilverstripeSocialFeed\Provider;
use SilverstripeSocialFeed\Jobs\SocialFeedCacheQueuedJob;
use Silverstripe\Control\Director;
use Silverstripe\Control\Controller;
use Silverstripe\Forms\LiteralField;
use Silverstripe\ORM\DataObject;
use Silverstripe\ORM\ArrayList;
use Silverstripe\ORM\DB;
use Silverstripe\Core\Convert;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Injector\Injector;
use Exception;
use DateTime;

class SocialFeedProvider extends DataObject  implements ProviderInterface
{

	/**
	 * Defines the database table name
	 * @var string
	 */
	private static $table_name = 'SocialFeedProvider';

	private static $db = array(
		'Label' => 'Varchar(100)',
		'Enabled' => 'Boolean'
	);

	private static $summary_fields = array(
		'Label' => 'Label',
		'Enabled.Nice' => 'Enabled'
	);

	/**
	 * Add default values to database
	 * @var array
	 */
	private static $defaults = [
		'Enabled' => 1
	];

	/**
	 * Defines a default list of filters for the search context
	 * @var array
	 */
	private static $searchable_fields = [
		'Label',
		'Enabled',
	];

	/**
	 * Then length of time it takes for the cache to expire
	 *
	 * @var int
	 */
	private static $default_cache_lifetime = 1800; // 15 minutes (900 seconds)

	public function getTitle() {
		return $this->Label;
	}

	/**
	 * @return FieldList
	 */
	public function getCMSFields() {
		if (Controller::has_curr())
		{
			if (isset($_GET['socialfeedclearcache']) && $_GET['socialfeedclearcache'] == 1 && $this->canEdit()) {
				$this->clearFeedCache();
				$url =  Controller::curr()->getRequest()->getVar('url');
				$urlAndParams = explode('?', $url);
				Controller::curr()->redirect($urlAndParams[0]);
			}

			$this->beforeUpdateCMSFields(function($fields) {
				$cache = $this->getFeedCache();
				if ($cache !== null && $cache !== false) {
					$url = Controller::curr()->getRequest()->getVar('url');
					$url .= '?socialfeedclearcache=1';
					$fields->addFieldToTab('Root.Main', LiteralField::create('cacheclear', '<a href="'.$url.'" class="field ss-ui-button ui-button" style="max-width: 100px;">Clear Cache</a>'));
				}
			});
		}
		$fields = parent::getCMSFields();
		return $fields;
	}

	public function getType() {
		throw new Exception("Do not instantiate {$this->ClassName}::getType");
	}
	public function getPostContent($post, $strip_html = true) {
		throw new Exception("Do not instantiate {$this->ClassName}::getPostContent");
	}
	public function getRawPostContent($post) {
		throw new Exception("Do not instantiate {$this->ClassName}::getRawPostContent");
	}
	public function getPostCreated($post) {
		throw new Exception("Do not instantiate {$this->ClassName}::getPostCreated");
	}
	public function getPostUrl($post) {
		throw new Exception("Do not instantiate {$this->ClassName}::getPostUrl");
	}
	public function getUserName($post) {
		throw new Exception("Do not instantiate {$this->ClassName}::getUserName");
	}
	public function getImage($post) {
		throw new Exception("Do not instantiate {$this->ClassName}::getImage");
	}
	public function getImageLowRes($post) {
		throw new Exception("Do not instantiate {$this->ClassName}::getImageLowRes");
	}
	public function getImageThumb($post) {
		throw new Exception("Do not instantiate {$this->ClassName}::getImageThumb");
	}


	/**
	 * Given some text, process it and return is as an HTMLText field, maybe for a template
	 * @param string $text
	 * @param boolean strip_html
	 * @returns SilverStripe\ORM\FieldType\DBHTMLText
	 */
	public function processTextContent($text, $strip_html = true) {
		if($strip_html) {
			$text = strip_tags($text);
		}
		// replace any links with <a> tags
		$text = $this->replaceLinks($text);
		$result = DBField::create_field(DBHTMLText::class, $text);
		return $result;
	}

	/**
	 * Given a string that may contain URLs, replace these URLs with <a> tags
	 */
	protected function replaceLinks($string, $target = "_blank") {
		$pattern = '/(https?:\/\/[^\s]+)/i';
		$target_attribute = "";
		if($target) {
			$target_attribute = " target=\"{$target}\"";
		}
		$string = preg_replace($pattern, "<a href=\"$1\"{$target_attribute}>$1</a>", $string);
		return $string;
	}

	/**
	 * Factory method to get Enabled social feed providers of the provided class name. You do the sorting if there is more than one provider returned.
	 * @param string $provider_class_name representing a provider that is a child of {@link SilverstripeSocialFeed\Provider\SocialFeedProvider}
	 * @param mixed  $enabled true|false|null - whether to return enabled/not enabled/all matching providers. The default is "Enabled=1"
	 * @returns Silverstripe\ORM\DataList
	 */
	public static function getProvider($provider_class_name, $enabled = true) {
		$sng = singleton($provider_class_name);
		if(!($sng instanceof SocialFeedProvider) || $provider_class_name == 'SocialFeedProvider') {
			return;
		}
		$list =  DataObject::get( $sng->getClassName() );
		if(!is_null($enabled)) {
			$list = $list->filter( ['Enabled' => $enabled] );
		}
		return $list;
	}

	/**
	 * Get feed from provider, will automatically cache the result.
	 *
	 *
	 * @return SS_List
	 */
	public function getFeed() {
		$feed = $this->getFeedCache();
		if (!$feed) {
			$feed = $this->getFeedUncached();
			$this->extend('updateFeedUncachedData', $feed);
			$this->setFeedCache($feed);
			singleton( SocialFeedCacheQueuedJob::class )->createJob($this);
		}

		$data = array();
		if ($feed) {
			foreach ($feed as $post) {
				$created = DBDatetime::create();
				$post_created_date = $this->getPostCreated($post);
				$dt = new DateTime( $post_created_date );
				$created->setValue( $dt->format( DateTime::ISO8601 ) );
				$data[] = array(
					'Type' => $this->getType(),
					'Content' => $this->getPostContent($post),
					'RawContent' => $this->getPostContent($post, false),
					'Created' => $created,
					'URL' => $this->getPostUrl($post),
					'Data' => $post,
					'UserName' => $this->getUserName($post),
					'Image' => $this->getImage($post),
					'ImageLowRes' => $this->getImageLowRes($post),
					'ImageThumb' => $this->getImageThumb($post),
				);
			}
		}

		$result = ArrayList::create($data);
		$result = $result->sort('Created', 'DESC');
		return $result;
	}

	/**
	 * Retrieve the providers feed without checking the cache first.
	 * @throws Exception
	 */
	public function getFeedUncached() {
		throw new Exception($this->class.' missing implementation for '.__FUNCTION__);
	}

	/**
	 * Mirror ModelAdmin::sanitiseClassName handling
	 */
	public function sanitiseClassName() {
		$sanitised = str_replace('\\', '-', $this->ClassName);//sanitise
		return $sanitised;
	}

	private function getFeedCacheKey() {
		$key = $this->sanitiseClassName() . "_" . $this->ID;
		return $key;
	}

	/**
	 * Get the providers feed from the cache. If there is no cache
	 * then return false.
	 *
	 * @return array
	 */
	public function getFeedCache() {
		$cache = $this->getCacheFactory();
		$key = $this->getFeedCacheKey();
		$feed = $cache->get($key);
		return json_decode($feed);
	}

	/**
	 * Set the cache.
	 */
	public function setFeedCache(array $feed, $lifetime = null) {
		$cache = $this->getCacheFactory();
		if($lifetime == null) {
			$lifetime = $this->config()->default_cache_lifetime;
		}
		$key  = $this->getFeedCacheKey();
		$result = $cache->set($key, json_encode($feed), $lifetime);
		return $result;
	}

	/**
	 * Clear the cache that holds this provider's feed.
	 */
	public function clearFeedCache() {
		$cache = $this->getCacheFactory();
		$key = $this->getFeedCacheKey();
		return $cache->remove($key);
	}

	/**
	 * Returns caching provider
	 */
	protected function getCacheFactory() {
		$cache = Injector::inst()->get(CacheInterface::class . '.socialfeedcache');
		return $cache;
	}
}
