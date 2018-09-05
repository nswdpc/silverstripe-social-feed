<?php
namespace SilverstripeSocialFeed\Provider;
use Silverstripe\Forms\LiteralField;
use Silverstripe\Forms\RequiredFields;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use Abraham\TwitterOAuth\TwitterOAuth;
use Exception;

class TwitterProvider extends SocialFeedProvider implements ProviderInterface
{

	/**
	 * Defines the database table name
	 * @var string
	 */
	private static $table_name = 'SocialFeedProviderTwitter';

	private static $db = array (
		'ConsumerKey' => 'Varchar(400)',
		'ConsumerSecret' => 'Varchar(400)',
		'AccessToken' => 'Varchar(400)',
		'AccessTokenSecret' => 'Varchar(400)',
        'ScreenName' => 'Varchar',
	);

	private static $singular_name = 'Twitter Provider';
	private static $plural_name = 'Twitter Providers';

	private $type = 'twitter';

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->addFieldsToTab('Root.Main', new LiteralField('sf_html_1', '<h4>To get the necessary Twitter API credentials you\'ll need to create a <a href="https://apps.twitter.com" target="_blank">Twitter App.</a></h4>'), 'Label');
		$fields->addFieldsToTab('Root.Main', new LiteralField('sf_html_2', '<p>You can manually grant permissions to the Twitter App, this will give you an Access Token and Access Token Secret.</h5><p>&nbsp;</p>'), 'Label');
		return $fields;
	}

	public function getCMSValidator()
	{
		return new RequiredFields(array('ConsumerKey', 'ConsumerSecret'));
	}

	/**
	 * Return the type of provider
	 *
	 * @return string
	 */
	public function getType()
	{
		return $this->type;
	}

	public function getFeedUncached()
	{
		// NOTE: Twitter doesn't implement OAuth 2 so we can't use https://github.com/thephpleague/oauth2-client
		$connection = new TwitterOAuth($this->ConsumerKey, $this->ConsumerSecret, $this->AccessToken, $this->AccessTokenSecret);
        $parameters = ['count' => 25, 'exclude_replies' => true];
        if($this->ScreenName)
        {
            $parameters['screen_name'] = $this->ScreenName;
        }
		$result = $connection->get('statuses/user_timeline', $parameters);
		if (isset($result->error)) {
			user_error($result->error, E_USER_WARNING);
		}
		return $result;
	}

	/**
	 * @return DBHTMLText
	 * @param stdClass $post
	 * @param boolean $strip_html default true. If you want the raw content, set this to false or class getRawPostContent. It's up to you to render the content safely
	 * @returns SilverStripe\ORM\FieldType\DBHTMLText
	 */
	public function getPostContent($post, $strip_html = true) {
		$text = isset($post->text) ? $post->text : '';
		return parent::processTextContent($text, $strip_html);
	}

	/**
	 * Return the post content *with* HTML unstripped
	 * @returns SilverStripe\ORM\FieldType\DBHTMLText
	 */
	public function getRawPostContent($post) {
		$text = $this->getPostContent($post, false);
		return $text;
	}

	/**
	 * Get the creation time from a post
	 *
	 * @param $post
	 * @return mixed
	 */
	public function getPostCreated($post)
	{
		return isset($post->created_at) ? $post->created_at : '';
	}

	/**
	 * Get the post URL from a post
	 *
	 * @param $post
	 * @return mixed
	 */
	public function getPostUrl($post)
	{
		if(isset($post->user->id) && isset($post->id)) {
			return 'https://twitter.com/' . (string) $post->user->id .'/status/' . (string) $post->id;
		} else {
			return '';
		}
	}

	/**
	 * The user's name who tweeted
	 *
	 * @param $post
	 * @return mixed
	 */
	public function getUserName($post)
	{
		return isset($post->user->name) ? $post->user->name : '';
	}

	/**
	 * The first image for a Tweet
	 *
	 * @param $post
	 * @return mixed
	 */
	public function getImage($post)
	{
		return isset($post->entities->media[0]->media_url_https) ? $post->entities->media[0]->media_url_https : '';
	}

	/**
	 * Twitter's low res version of the feed image ~400w
	 */
	public function getImageLowRes($post)
	{
		if($image = $this->getImage($post)) {
			return $image . ":small";
		}
	}

	/**
	 * Twitter's thumb version of the feed image ~150w
	 */
	public function getImageThumb($post)
	{
		if($image = $this->getImage($post)) {
			return $image . ":thumb";
		}
	}

	public function getPostType($post) {
		return null;
	}
}
