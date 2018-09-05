<?php
namespace SilverstripeSocialFeed\Provider;
use Silverstripe\Forms\LiteralField;
use Silverstripe\Forms\DropdownField;
use Silverstripe\Forms\RequiredFields;
use SilverStripe\ORM\FieldType\DBField;
use League\OAuth2\Client\Provider\Facebook;
use Exception;
use DateTime;

class FacebookProvider extends SocialFeedProvider implements ProviderInterface
{

	/**
	 * Defines the database table name
	 * @var string
	 */
	private static $table_name = 'SocialFeedProviderFacebook';

	private static $db = array(
		'FacebookPageID' => 'Varchar(100)',
		'FacebookAppID' => 'Varchar(400)',
		'FacebookAppSecret' => 'Varchar(400)',
		'AccessToken' => 'Varchar(400)',
		'FacebookType' => 'Int',
	);

	private static $singular_name = 'Facebook Provider';
	private static $plural_name = 'Facebook Providers';

	private static $summary_fields = array(
		'FacebookPageID'
	);

	const POSTS_AND_COMMENTS = 0;
	const POSTS_ONLY = 1;
	private static $facebook_types = array(
		self::POSTS_AND_COMMENTS => 'Page Posts and Comments',
		self::POSTS_ONLY 		 => 'Page Posts Only'
	);

	private $type = 'facebook';

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();
		$fields->addFieldsToTab('Root.Main', new LiteralField('sf_html_1', '<h4>To get the necessary Facebook API credentials you\'ll need to create a <a href="https://developers.facebook.com/apps" target="_blank">Facebook App.</a></h4><p>&nbsp;</p>'), 'Label');
		$fields->replaceField('FacebookType', DropdownField::create('FacebookType', 'Facebook Type', $this->config()->facebook_types));
		return $fields;
	}

	public function getCMSValidator()
	{
		return new RequiredFields(array('FacebookPageID', 'FacebookAppID', 'FacebookAppSecret'));
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

	public function getPostType($post) {
		return isset($post['type']) ? $post['type'] : '';
	}

	public function getFeedUncached()
	{
		$options = [
			'clientId' => $this->FacebookAppID,
			'clientSecret' => $this->FacebookAppSecret,
			// https://github.com/thephpleague/oauth2-facebook#graph-api-version
			'graphApiVersion' => 'v3.1'
		];
		$provider = new Facebook($options);

		// For an App Access Token we can just use our App ID and App Secret pipped together
		// https://developers.facebook.com/docs/facebook-login/access-tokens#apptokens
		$accessToken = ($this->AccessToken) ? $this->AccessToken : $this->siteConfig->SocialFeedFacebookAppID . '|' . $this->siteConfig->SocialFeedFacebookAppSecret;

		// Setup query params for FB query
		$queryParameters = array(
			// Get Facebook timestamps in Unix timestamp format
			'date_format'  => 'U',
			// Explicitly supply all known 'fields' as the API was returning a minimal fieldset by default.
			'fields'	   => 'from,message,message_tags,story,story_tags,full_picture,picture,attachments,source,link,object_id,name,caption,description,icon,privacy,type,status_type,created_time,updated_time,shares,is_hidden,is_expired,likes,comments',
			'access_token' => $accessToken,
			'appsecret_proof' => hash_hmac('sha256', $accessToken, $this->FacebookAppSecret),
		);
		$queryParameters = http_build_query($queryParameters);

		// Get all data for the FB page
		switch ($this->FacebookType) {
			case self::POSTS_AND_COMMENTS:
				$request = $provider->getRequest('GET', 'https://graph.facebook.com/' . $this->FacebookPageID . '/feed?'.$queryParameters);
			break;

			case self::POSTS_ONLY:
				$request = $provider->getRequest('GET', 'https://graph.facebook.com/' . $this->FacebookPageID . '/posts?'.$queryParameters);
			break;

			default:
				throw new Exception('Invalid FacebookType ('.$this->FacebookType.')');
			break;
		}
		$result = $provider->getResponse($request);

		return $result['data'];
	}

	/**
	 * @return HTMLText
	 */
	public function getPostContent($post, $strip_html = true) {
		$text = isset($post['message']) ? $post['message'] : '';
		return parent::processTextContent($text, $strip_html);
	}

	/**
	 * Get the creation time from a post.
	 * created_time is a UNIX timestamp
	 *
	 * @param $post
	 * @return mixed
	 */
	public function getPostCreated($post)
	{
		$created_time = isset($post['created_time']) ? $post['created_time'] : '';
		if($created_time) {
			$created_time = gmdate(DateTime::ISO8601, $created_time);
		}
		return $created_time;
	}

	/**
	 * Get the post URL from a post
	 *
	 * @param $post
	 * @return mixed
	 */
	public function getPostUrl($post)
	{
		if (!empty($post['link'])) {
			return $post['link'];
		}
		return null;
	}

	/**
	 * Get the user who made the post
	 *
	 * @param $post
	 * @return mixed
	 */
	public function getUserName($post)
	{
		return isset($post['from']['name']) ? $post['from']['name'] : '';
	}

	/**
	 * Get the primary image for the post
	 *
	 * @param $post
	 * @return mixed
	 */
	public function getImage($post)
	{
		return isset($post['full_picture']) ? $post['full_picture'] : '';
	}

	/**
	 * Get the low res image for the post, which is currently just the full_picture as FB only returns either "full_picture" or "picture"
	 *
	 * @param $post
	 * @return mixed
	 */
	public function getImageLowRes($post)
	{
		return $this->getImage($post);
	}

	/**
	 * Get the thumb image for the post
	 * The docs say:
	 * 		"URL to a resized version of the Photo published in the Post or scraped from a link in the Post.
	 * 		If the photo's largest dimension exceeds 130 pixels, it will be resized, with the largest dimension set to 130."
	 *
	 * @param $post
	 * @return mixed
	 */
	public function getImageThumb($post)
	{
		return isset($post['picture']) ? $post['picture'] : '';
	}
}
