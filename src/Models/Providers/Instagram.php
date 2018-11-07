<?php
namespace SilverstripeSocialFeed\Provider;
use Silverstripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\DropdownField;
use Silverstripe\Control\Director;
use Silverstripe\Forms\RequiredFields;
use SilverStripe\ORM\FieldType\DBField;
use League\OAuth2\Client\Provider\Facebook;
use GuzzleHttp\Client as GuzzleHttpClient;
use Exception;
use DateTime;

/**
 * InstagramProvider using the Facebook Graph API. Note that this is not complete due to API changes.
 * Use the InstagramBasicProvider to aggregate your own feed
 */
class InstagramProvider extends FacebookProvider implements ProviderInterface
{

    protected $enabled_api_client = false;

    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'SocialFeedProviderInstagram';

    private static $db = array(
        'InstagramBusinessAccountId' => 'Varchar(255)',
		'InstagramUsername' => 'Varchar(255)',
    );

    /**
     * Has_one relationship
     * @var array
     */
    private static $has_one = [
        'FacebookProvider' => FacebookProvider::class, // use the credentials of this Facebook app
    ];

    private static $singular_name = 'Instagram Provider';
    private static $plural_name = 'Instagram Providers';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->addFieldToTab(
            'Root.Main',
            LiteralField::create(
                'InstgramInstructions',
                '<p class="message">'
                . _t('SocialFeed.InstgramInstructions', 'The Instagram API now uses the Facebook Graph API. Select an existing Facebook Provider or create a new set of authentication tokens below.')
                . '</p>'
            ),
            'FacebookHelpInformation'
        );

		$fields->addFieldToTab(
			'Root.Main',
			TextField::create(
				'InstagramBusinessAccountId',
				'Instagram Business Account Id'
			)->setDescription('Leave empty to automatically retrieve this using the saved Facebook Page Access Token'),
			'Label'
		);

		$fields->addFieldToTab(
			'Root.Main',
			TextField::create(
				'InstagramUsername',
				'Instagram Username'
			)->setDescription('Used for business disovery'),
			'Label'
		);

        $fields->addFieldToTab(
            'Root.Main',
            DropdownField::create(
                'FacebookProviderID',
                'Use this Facebook Provider (or enter values below)',
                FacebookProvider::get()->filter(['Enabled' => 1, 'ClassName' => FacebookProvider::class ])->map('ID','Label')
            )->setEmptyString(''),
            'FacebookHelpInformation'
        );

        if($this->FacebookProviderID) {
            $fields->removeByName([
                'FacebookType',
                'FacebookPageAccessToken',
                'FacebookUserAccessToken',
                'FacebookPageType',
                'FacebookPageID',
                'FacebookAppID',
                'FacebookAppSecret',
                'FacebookUserAccessTokenExpires',
                'FacebookPageAccessTokenCreated',
                'FacebookPageAccessTokenExpires',
                'CreatePageAccessToken'
            ]);
        }

        return $fields;
    }

    public function getCMSValidator()
    {
        return new RequiredFields();
    }

    private function validateFacebookProvider() {
        $provider = $this->FacebookProvider();
        if(!empty($provider->ID) && ($provider instanceof FacebookProvider) && $provider->ClassName == FacebookProvider::class) {
            return $provider;
        }
        return false;
    }

    public function getFacebookPageAccessToken() {
        if($provider = $this->validateFacebookProvider()) {
            return $provider->FacebookPageAccessToken;
        } else {
            return $this->getField('FacebookPageAccessToken');
        }
    }

    public function getFacebookUserAccessToken() {
        if($provider = $this->validateFacebookProvider()) {
            return $provider->FacebookUserAccessToken;
        } else {
            return $this->getField('FacebookUserAccessToken');
        }
    }

    public function getFacebookAppSecret() {
        if($provider = $this->validateFacebookProvider()) {
            return $provider->FacebookAppSecret;
        } else {
            return $this->getField('FacebookAppSecret');
        }
    }

    public function getFacebookAppID() {
        if($provider = $this->validateFacebookProvider()) {
            return $provider->FacebookAppID;
        } else {
            return $this->getField('FacebookAppID');
        }
    }

    public function getFacebookPageID() {
        if($provider = $this->validateFacebookProvider()) {
            return $provider->FacebookPageID;
        } else {
            return $this->getField('FacebookPageID');
        }
    }

    /**
     * Event handler called before writing to the database.
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

		//deprecated fields
        $this->ClientID = "";
        $this->ClientSecret = "";
        $this->AccessToken = "";
    }

    /**
     * Return the type of provider
     *
     * @return string
     */
    public function getType()
    {
        return parent::PROVIDER_INSTAGRAM;
    }

	/**
	 * Given the configured page access token, try to get the instagram_business_account id
	 * See: https://developers.facebook.com/docs/instagram-api/reference/page/ and note the permissions required
	 */
	protected function RequestInstagramBusinessAccountId() {
		if(!$this->FacebookPageAccessToken) {
			return "";
		}
		if(!$this->FacebookPageID) {
			return "";
		}
		$appsecret_proof = $this->GetAppSecretProof($this->FacebookPageAccessToken);
		$url = "https://graph.facebook.com"
						. "/{$this->FacebookPageID}"
						. "?fields=instagram_business_account"
						. "&access_token={$this->FacebookPageAccessToken}"
						. "&appsecret_proof={$appsecret_proof}";
		$error = "";
		try {
			$client = new GuzzleHttpClient();
			$options = [];
			$response = $client->request("GET", $url, $options);
			$body = $response->getBody()->getContents();
			if(!empty($encoded['instagram_business_account']['id'])) {
				return $encoded['instagram_business_account']['id'];
			}
			$error = "Instagram: no instagram_business_account.id in request response";
		} catch (Exception $e) {
			$error = $e->getMessage();
		}
		throw new Exception($error);
	}

	public function getFeedUncached()
    {
		if(empty($this->InstagramUsername)) {
			throw new Exception("No Instagram Username provided");
		}
        if(empty($this->FacebookPageAccessToken)) {
            $this->GetPageAccessToken();
            if(empty($this->FacebookPageAccessToken)) {
                // could not get a facebook page access token
                // even after trying harder
                throw new Exception("Facebook: could not create/retrieve a page access token");
            } else {
                // write the value found
                $this->UnsetWriteModifiers();
                $this->write();
            }
        }

		if(empty($this->InstagramBusinessAccountId)) {
			if($instagram_business_account_id = $this->RequestInstagramBusinessAccountId()) {
				$this->InstagramBusinessAccountId = $instagram_business_account_id;
				$this->UnsetWriteModifiers();
				$this->write();
			} else {
				throw new Exception("Failed to retrieve the instagram_business_account.id");
			}
		}
        $options = [
            'clientId' => $this->FacebookAppID,
            'clientSecret' => $this->FacebookAppSecret,
            // https://github.com/thephpleague/oauth2-facebook#graph-api-version
            'graphApiVersion' => 'v3.1'
        ];
        $provider = new Facebook($options);

        // Setup query params for FB query
        $queryParameters = array(
            // Get Facebook timestamps in Unix timestamp format
            'date_format'  => 'U',
            // Explicitly supply all known 'fields' as the API was returning a minimal fieldset by default.
            'fields'       => "business_discovery.username({$this->InstagramUsername}){followers_count,media_count,media}",
            'access_token' => $this->FacebookPageAccessToken,
            'appsecret_proof' => $this->GetAppSecretProof($this->FacebookPageAccessToken)
        );

        $queryParameters = http_build_query($queryParameters);

		$request_url = 'https://graph.facebook.com/' . $this->InstagramBusinessAccountId . '/?'.$queryParameters;

        $request = $provider->getRequest('GET', $request_url);
        $result = $provider->getResponse($request);

        return $result['data'];
    }

    public function getPostType($post) {
        return isset($post['type']) ? $post['type'] : '';
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
     *         "URL to a resized version of the Photo published in the Post or scraped from a link in the Post.
     *         If the photo's largest dimension exceeds 130 pixels, it will be resized, with the largest dimension set to 130."
     *
     * @param $post
     * @return mixed
     */
    public function getImageThumb($post)
    {
        return isset($post['picture']) ? $post['picture'] : '';
    }
}
