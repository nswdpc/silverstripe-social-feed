<?php
namespace SilverstripeSocialFeed\Provider;
use Silverstripe\Forms\LiteralField;
use SilverStripe\Forms\CheckboxField;
use Silverstripe\Forms\DropdownField;
use Silverstripe\Forms\TextareaField;
use Silverstripe\Forms\TextField;
use Silverstripe\Forms\RequiredFields;
use SilverStripe\ORM\FieldType\DBField;
use Instagram\Api as InstagramBasicAPI;
use Silverstripe\Control\Director;
use GuzzleHttp\Client as GuzzleHttpClient;
use Exception;
use DateTime;

class InstagramBasicProvider extends SocialFeedProvider implements ProviderInterface {

    protected $enabled_api_client = true;

    private static $singular_name = 'Instagram Basic Provider';
    private static $plural_name = 'Instagram Basic Providers';

    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'SocialFeedProviderInstagramBasic';

    private static $db = array(
        'ClientID' => 'Varchar(255)',
        'ClientSecret' => 'Varchar(255)',
        'InstagramUserId' => 'Varchar(255)',
        'InstagramUsername' => 'Varchar(255)',
        'InstagramAccessToken' => 'Varchar(255)',
    );

    private $authorisation_url = "https://api.instagram.com/oauth/authorize/";
    //"?client_id=xxx&redirect_uri=http://localhost:3000&response_type=token&scope=public_content

    /**
     * Returns the URL used to get the access token
     */
    public function getAuthURL() {
        $url = $this->authorisation_url
                . "?client_id={$this->ClientID}"
                . "&response_type=code"
                . "&redirect_uri=" . urlencode($this->getRedirectUrl());
        return $url;
    }

    /**
     * Construct redirect URI using current class name - used during OAuth flow.
     * @return string
     */
    private function getRedirectUrl()
    {
        return Director::absoluteBaseURL() . 'social-feed-authorise?type=' . $this->getType() . '&provider=' . $this->ID;
    }

    /**
     * Return the type of provider
     *
     * @return string
     */
    public function getType()
    {
        return parent::PROVIDER_INSTAGRAM_BASIC;
    }

	/*
	 * This method is called from the controller
	 	error: access_denied
		error_reason: user_denied
		error_description: The user denied your request

		curl -F 'client_id=CLIENT_ID' \
-F 'client_secret=CLIENT_SECRET' \
-F 'grant_type=authorization_code' \
-F 'redirect_uri=AUTHORIZATION_REDIRECT_URI' \
-F 'code=CODE' \
https://api.instagram.com/oauth/access_token

	 */
	public function finaliseAuthorisation($params) {
		$code = isset($params['code']) ? $params['code'] : '';
		if(!$code) {
			throw new Exception("No 'code' param provided");
		}

		if(!$this->ClientID || !$this->ClientSecret) {
			throw new Exception("Missing configuration for provider #{$this->ID} - clientid and/or secret");
		}

		$client = new GuzzleHttpClient();
		$url = "https://api.instagram.com/oauth/access_token";

		$params = [
			"client_id" => $this->ClientID,
			"client_secret" => $this->ClientSecret,
			"grant_type" => "authorization_code",
			"redirect_uri" => $this->getRedirectUrl(), // this is/must be the same as the original request
			"code" => $code
		];

		//print "<pre>";print_r($params);exit;

		$options = ['form_params' => $params ];
		$response = $client->request('POST', $url, $options);
		$body = $response->getBody()->getContents();
		$encoded = json_decode($body, true);
		if(empty($encoded['access_token'])) {
			throw new Exception("No access_token found in response from api.instagram.com");
		}
		/*
		* sample response
		{
		    "access_token": "fb2e77d.47a0479900504cb3ab4a1f626d174d2d",
		    "user": {
		        "id": "1574083",
		        "username": "snoopdogg",
		        "full_name": "Snoop Dogg",
		        "profile_picture": "..."
		    }
		}
		 */
		$this->InstagramAccessToken = $encoded['access_token'];
		$this->write();
		$redirect_link = $this->itemEditLink();
		header("Location: {$redirect_link}");
		exit;
	}

    public function getCMSValidator()
    {
        return new RequiredFields();
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->addFieldToTab(
            'Root.Main',
            LiteralField::create(
                'InstagramInstructions',
                '<p class="message notice">'
                . _t('SocialFeed.InstgramBasicInstructions', 'The Instagram Basic provider allows you to aggregate media from your own account (until ~2020).')
                . '</p>'
            ),
            'Label'
        );

        if($this->ClientID && $this->ClientSecret) {

			$auth_url = $this->getAuthURL();

			$fields->addFieldToTab(
	            'Root.Main',
	            LiteralField::create(
	                'InstagramAuthInstructions',
	                '<p class="message">'
					. _t('SocialFeed.InstagramBasicAuthIntro', 'To use this provider you must get an access token from Instagram at ')
					. "<br>"
	                . "<code><a href=\"{$auth_url}\">{$auth_url}</a></code>"
					. "<br>"
					. _t('SocialFeed.InstagramBasicAuthHelp', "Please ensure that you are signed into the Instagram account you wish to retrieve a feed from.")
	                . '</p>'
	            ),
				'Label'
	        );

		} else {
			$fields->addFieldToTab(
	            'Root.Main',
	            LiteralField::create(
	                'InstagramAuthInstructions',
	                '<p class="message error">'
					. _t('SocialFeed.InstagramAuthMissingClientFields', 'To authorise this application you must save the client ID and client secret from https://www.instagram.com/developer/clients/manage/')
	                . '</p>'
	            ),
				'Label'
	        );
		}

		$fields->addFieldToTab(
			'Root.Main',
			TextField::create(
				'InstagramAccessToken',
				'Instagram Access Token'
			)->setDescription('Your instagram access token'),
			'Label'
		);

		$fields->makeFieldReadonly( $fields->dataFieldByName('InstagramAccessToken') );

        $fields->addFieldToTab(
            'Root.Main',
            TextField::create(
                'ClientID',
                'Instagram Client Id'
            )->setDescription('Your instagram client id'),
            'Label'
        );


        $fields->addFieldToTab(
            'Root.Main',
            TextField::create(
                'ClientSecret',
                'Instagram client secret'
            )->setDescription('Your instagram client secret'),
            'Label'
        );

        $fields->addFieldToTab(
            'Root.Main',
            TextField::create(
                'InstagramUserId',
                'Instagram User Id'
            )->setDescription('Your instagram user id'),
            'Label'
        );

        $fields->addFieldToTab(
            'Root.Main',
            TextField::create(
                'InstagramUsername',
                'Instagram Username'
            )->setDescription('Your instagram user name (the x\'s from instagram.com/xxxxxx)'),
            'Label'
        );

        return $fields;
    }




    public function getFeedUncached()
    {
        if(empty($this->InstagramUsername)) {
            throw new Exception("No Instagram Username provided");
        }

        if(empty($this->InstagramAccessToken)) {
            throw new Exception("No Instagram Access Token provided");
        }

        if(empty($this->InstagramUserId)) {
            throw new Exception("No Instagram User Id provided");
        }

        $api = new \Instagram\Api();

        $api->setAccessToken($this->InstagramAccessToken);
        $api->setUserId($this->InstagramUserId);

        $result = $api->getFeed($this->InstagramUsername);

        if(!$result || empty($result->medias)) {
            return false;
        }
        return (array)$result->medias;

        /*
        Instagram\Hydrator\Feed Object
        (
            [id] => 184263228
            [userName] => pgrimaud
            [fullName] => Pierre G
            [biography] => Gladiator retired - ESGI 14
            [followers] => 342
            [following] => 114
            [profilePicture] => https://scontent.cdninstagram.com/vp/f49bc1ac9af43314d3354b4c4a987c6d/5B5BB12E/t51.2885-19/10483606_1498368640396196_604136733_a.jpg
            [externalUrl] => https://p.ier.re/
            [mediaCount] => 33
            [hasNextPage] => 1
            [maxId] => 1230468487398454311_184263228
            [medias] => Array
                (
                    [0] => Instagram\Hydrator\Media Object
                        (
                            [id] => 1758133053345287778_184263228
                            [typeName] => image
                            [height] => 640
                            [width] => 640
                            [thumbnailSrc] => https://scontent.cdninstagram.com/vp/e64c51de7f5401651670fd0bbdfd9837/5B69AF2B/t51.2885-15/s150x150/e35/30604700_183885172242354_7971196573931536384_n.jpg
                            [link] => https://www.instagram.com/p/BhmJLJwhM5i/
                            [date] => DateTime Object
                                (
                                    [date] => 2018-04-15 17:23:33.000000
                                    [timezone_type] => 3
                                    [timezone] => Europe/Paris
                                )

                            [displaySrc] => https://scontent.cdninstagram.com/vp/dd39e08d3c740e764c61bc694d36f5a7/5B643B2F/t51.2885-15/s640x640/sh0.08/e35/30604700_183885172242354_7971196573931536384_n.jpg
                            [caption] =>
                            [comments] => 2
                            [likes] => 14
                        )
                    ...
                )
        )
        */
    }

    public function getPostType($post) {
        return isset($post['typeName']) ? $post['typeName'] : '';
    }

    /**
     * @return HTMLText
     */
    public function getPostContent($post, $strip_html = true) {
        $text = isset($post['caption']) ? $post['caption'] : '';
        return parent::processTextContent($text, $strip_html);
    }

    /**
     * Get the creation time from a post, 'date' is a \DateTime
     * @todo timezone
     *
     * @param $post
     * @return mixed
     */
    public function getPostCreated($post)
    {
        $created_datetime = isset($post['date']) && ($post['date'] instanceof DateTime) ? $post['date'] : '';
        return $created_datetime;
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
        return $this->InstagramUsername;
    }

    /**
     * Get the primary image for the post
     *
     * @param $post
     * @return mixed
     */
    public function getImage($post)
    {
        return isset($post['displaySrc']) ? $post['displaySrc'] : '';
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
     *         "URL to a resized version of the Photo published in the Post or scraped from a link in the Post.
     *         If the photo's largest dimension exceeds 130 pixels, it will be resized, with the largest dimension set to 130."
     *
     * @param $post
     * @return mixed
     */
    public function getImageThumb($post)
    {
        return isset($post['thumbnailSrc']) ? $post['thumbnailSrc'] : '';
    }



}
