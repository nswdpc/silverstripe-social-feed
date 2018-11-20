<?php
namespace SilverstripeSocialFeed\Provider;
use SilverstripeSocialFeed\Jobs\SocialFeedCacheQueuedJob;
use Silverstripe\Control\Director;
use Silverstripe\Control\Controller;
use Silverstripe\Forms\CheckboxField;
use Silverstripe\Forms\DropdownField;
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
use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\ValidationException;
use Exception;
use DateTime;

class SocialFeedProvider extends DataObject implements ProviderInterface
{

	private static $description = '';
	private static $singular_name = '';
	private static $plural_name = '';

	const PROVIDER_FACEBOOK = 'facebook';
	const PROVIDER_TWITTER = 'twitter';
	const PROVIDER_INSTAGRAM = 'instagram';
	const PROVIDER_INSTAGRAM_BASIC = 'instagrambasic';

	/**
	 * Defines the database table name
	 * @var string
	 */
	private static $table_name = 'SocialFeedProvider';

	private static $db = array(
		'Label' => 'Varchar(100)',
		'Enabled' => 'Boolean',
		'LastFeedError' => 'Text',
	);

	private static $summary_fields = array(
		'ID' => '#',
		'Label' => 'Label',
		'Type' => 'Type',
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

	/**
	 * Return the edit link to this item
	 */
	public function itemEditLink() {
		$slug = str_replace("\\", "-", SocialFeedProvider::class);
		return Director::absoluteURL( Controller::join_links('admin', 'social-feed', $slug, 'EditForm/field', $slug, 'item', $this->ID, 'edit') );
	}

	public function getTitle() {
		return $this->Label;
	}

	public function getAllowedFeedProviders() {
		$subclasses = ClassInfo::subclassesFor( self::class );
		$map = [];
		foreach($subclasses as $k=>$subclass) {
			if($subclass == self::class) {
				unset($subclasses[$k]);
				continue;
			}
			$sng = singleton($subclass);
			$map_key = preg_replace("|^SilverstripeSocialFeed\\\\Provider\\\\|", "", $subclass);
			$provider_description = $sng->config()->get('description');
			$map[ $map_key ] = $sng->singular_name() . ($provider_description ? " - {$provider_description}" : "");
		}
		return $map;
	}

	/**
	 * @return FieldList
	 */
	public function getCMSFields() {
		$fields = parent::getCMSFields();
		if(!$this->exists()) {
			foreach($fields->dataFields() as $field) {
				$fields->removeByName([
					$field->getName()
				]);
			}
			$subclasses = $this->getAllowedFeedProviders();
			$fields->addFieldToTab('Root.Main',
				DropdownField::create(
					'FeedProviderType',
					_t('SocialFeed.FEED_PROVIDER_TYPE','Choose the feed provider'),
					$subclasses
				)->setEmptyString('')
			);
		} else {
			$fields->addFieldToTab('Root.Main',
				CheckboxField::create(
					'RefreshFeedFromSource',
					_t('SocialFeed.REFRESH_FEED','Refresh feed from the source')
				),
				'Enabled'
			);
			// reporting errors
			$fields->makeFieldReadonly( $fields->dataFieldByName('LastFeedError'));
		}
		return $fields;
	}

	/**
	 * Event handler called before writing to the database.
	 */
	public function onBeforeWrite()
	{
		parent::onBeforeWrite();
		if(!$this->exists()) {
			if($this->FeedProviderType) {
				// initial write of child record
				$type = $this->FeedProviderType;
				if(!$type) {
					throw new ValidationException("Please select a feed provider");
				}
				$class_name = "SilverstripeSocialFeed\\Provider\\{$type}";
				if(class_exists($class_name)) {
					$inst = singleton($class_name);
					$inst->write();
					if(!$inst->ID) {
						throw new ValidationException("The record could not be saved at the current time");
					}
					$this->ID = $inst->ID;// transfer ID across, this operation then becomes an "edit"
				} else {
					throw new ValidationException("Unknown feed provider: {$type}");
				}
			}
		} else {
			$this->MaybeRefreshFeed();
		}
	}

	private function MaybeRefreshFeed() {
		if($this->RefreshFeedFromSource == 1) {
			try {
				$this->getFeedUncached();
			} catch (Exception $e) {
				$this->LastFeedError = $e->getMessage();
			}
		}
	}

	public function getType() {
		throw new Exception("Do not instantiate {$this->ClassName}::getType");
	}

	public function getPostType($post) {
		return null;
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
	 * Different providers can set certain properties to values to process certain tasks on write
	 * The provider class uses this method to unset/reset these and avoid circular writes, for instance
	 */
	protected function UnsetWriteModifiers() {
		$this->RefreshFeedFromSource = 0;
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
			try {
				$feed = $this->getFeedUncached();
				$this->extend('updateFeedUncachedData', $feed);
				$this->setFeedCache($feed);
			} catch (Exception $e) {
				// store an error for helping with issue resolution
				$this->LastFeedError = $e->getMessage();
				$this->UnsetWriteModifiers();
				$this->write();
			}
			singleton( SocialFeedCacheQueuedJob::class )->createJob('', $this->ID);
		}

		$data = [];
		if (is_array($feed) && !empty($feed)) {

			// clear last error
			$this->LastFeedError = '';
			$this->UnsetWriteModifiers();
			$this->write();

			foreach ($feed as $post) {
				$created = DBDatetime::create();
				$post_created_date = $this->getPostCreated($post);
				if(!($post_created_date instanceof DateTime)) {
					$dt = new DateTime( $post_created_date );
				} else {
					$dt = $post_created_date;
				}
				$created_date_formatted = $dt->format( DateTime::ISO8601 );
				$created->setValue( $created_date_formatted );
				$data[] = array(
					'Type' => $this->getType(),
					'PostType' => $this->getPostType($post),
					'Content' => $this->getPostContent($post),
					'RawContent' => $this->getPostContent($post, false),
					'Created' => $created,
					'CreatedDateTime' => $created_date_formatted,
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
		$result = $result->sort('CreatedDateTime', 'DESC');
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

	private function isFlushing() {
		$controller = Controller::curr();
		$request = $controller->getRequest();
		return !is_null($request->getVar('flush'));
	}

	/**
	 * Get the providers feed from the cache. If there is no cache
	 * then return false.
	 *
	 * @return array
	 */
	public function getFeedCache() {
		if( $this->isFlushing() ) {
			return false;
		}
		$cache = $this->getCacheFactory();
		$key = $this->getFeedCacheKey();
		$feed = $cache->get($key);
		return $this->hydrate($feed);
	}

	protected function hydrate($feed) {
		return unserialize($feed);
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
		$result = $cache->set($key, serialize($feed), $lifetime);
		return $result;
	}

	/**
	 * Clear the cache that holds this provider's feed.
	 */
	public function clearFeedCache() {
		$cache = $this->getCacheFactory();
		$key = $this->getFeedCacheKey();
		return $cache->delete($key);
	}

	/**
	 * Returns caching provider
	 */
	protected function getCacheFactory() {
		$cache = Injector::inst()->get(CacheInterface::class . '.socialfeedcache');
		return $cache;
	}

	public function finaliseAuthorisation($params) {
		return false;
	}
}
