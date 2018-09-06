<?php
namespace SilverstripeSocialFeed\Provider;
use Silverstripe\Forms\LiteralField;
use SilverStripe\Forms\CheckboxField;
use Silverstripe\Forms\DropdownField;
use Silverstripe\Forms\RequiredFields;
use SilverStripe\ORM\FieldType\DBField;
use League\OAuth2\Client\Provider\Facebook;
use GuzzleHttp\Client as GuzzleHttpClient;
use Exception;
use DateTime;

class FacebookProvider extends SocialFeedProvider implements ProviderInterface
{

    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'SocialFeedProviderFacebook';

    private static $db = [
        'FacebookPageID' => 'Varchar(100)',
        'FacebookAppID' => 'Varchar(400)',
        'FacebookAppSecret' => 'Varchar(400)',
        // initial user access token
        'FacebookUserAccessToken' => 'Text',
        // page access token details
        'FacebookPageAccessToken' => 'Text',
        'FacebookPageAccessTokenCreated' => 'Varchar(255)',
        'FacebookType' => 'Int',
    ];

    private static $singular_name = 'Facebook Provider';
    private static $plural_name = 'Facebook Providers';

    private static $summary_fields = [
        'FacebookPageID'
    ];

    const POSTS_AND_COMMENTS = 0;
    const POSTS_ONLY = 1;
    private static $facebook_types = [
        self::POSTS_AND_COMMENTS => 'Page Posts and Comments',
        self::POSTS_ONLY          => 'Page Posts Only'
    ];

    private $type = 'facebook';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldToTab('Root.Main',
            LiteralField::create('HelpInformation', '<p class="message">To get the necessary Facebook API credentials'
                 . ' you\'ll need to create a <a href="https://developers.facebook.com/apps" target="_blank">Facebook App.</a></p>'),
                'Label'
        );
        $fields->replaceField('FacebookType', DropdownField::create('FacebookType', 'Facebook Type', $this->config()->facebook_types));

        $fields->dataFieldByName('FacebookPageID')
                ->setDescription("This value can either be the page vanity name or the actual page id");

        $fields->dataFieldByName('FacebookPageAccessToken')
                        ->setDescription("You can verify this value at the access token debugger: https://developers.facebook.com/tools/debug/accesstoken/");

        $fields->dataFieldByName('FacebookUserAccessToken')
                ->setDescription('A short lived user access token, created by an admin for the page in question.')
                ->setRightTitle('See documentation for details on how to create this.');

        // allow to force recreate the page access token
        $fields->addFieldToTab('Root.Main',
            CheckboxField::create(
                'CreatePageAccessToken',
                'Create a new page access token'
            )
        );

        return $fields;
    }

    public function getCMSValidator()
    {
        return new RequiredFields(array('FacebookPageID', 'FacebookAppID', 'FacebookAppSecret'));
    }

    /**
     * Event handler called before writing to the database.
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->AccessToken = "";// ensure this deprecated value is empty
        if($this->CreatePageAccessToken == 1 || empty($this->FacebookPageAccessToken)) {
            $this->GetPageAccessToken();
        }
    }

    /**
     * Get a page access token, save it and the expiry returned
     */
    private function GetPageAccessToken() {
        $user_token_longlived = $this->RequestExchangeToken();
        if(!$user_token_longlived) {
            // cannot get a page access token without this
            return false;
        }
        $page_access_token = $this->RequestPageAccessToken($user_token_longlived);
        if($page_access_token) {
            $this->FacebookPageAccessToken = $page_access_token;
            $dt = new DateTime();
            $this->FacebookPageAccessTokenCreated = $dt->format("Y-m-d H:i:s");
            return true;
        }
        return false;
    }

    private function GetAppSecretProof($token) {
        return hash_hmac('sha256', $token, $this->FacebookAppSecret);
    }

    /**
     * Request a page access token based on the user token
     */
    private function RequestPageAccessToken($user_token) {
        $page_access_token = false;
        $appsecret_proof = $this->GetAppSecretProof($user_token);
        $url = "https://graph.facebook.com"
                        . "/{$this->FacebookPageID}"
                        . "?fields=access_token"
                        . "&access_token={$user_token}"
                        . "&appsecret_proof={$appsecret_proof}";
        try {
            $client = new GuzzleHttpClient();
            $options = [];
            $response = $client->request("GET", $url, $options);
			$body = $response->getBody()->getContents();
            $encoded = json_decode($body, true);
            if(!empty($encoded['access_token'])) {
                /**
                 * Sample response
                 * [access_token] => <token>
                 * [id] => page_id
                 */
                $page_access_token = $encoded['access_token'];
            }
        } catch (Exception $e) {
        }
        return $page_access_token;
    }

    /**
     * Request a long-lived user token (currently expires in 60 days)
     */
    private function RequestExchangeToken() {
        $user_token_longlived = false;
        $url = "https://graph.facebook.com"
                        . "/oauth/access_token"
                        . "?client_id={$this->FacebookAppID}"
                        . "&client_secret={$this->FacebookAppSecret}"
                        . "&grant_type=fb_exchange_token"
                        . "&fb_exchange_token={$this->FacebookUserAccessToken}";
        try {
            $client = new GuzzleHttpClient();
            $options = [];
            $response = $client->request("GET", $url, $options);
			$body = $response->getBody()->getContents();
			$encoded = json_decode($body, true);
            if(!empty($encoded['access_token'])) {
                /**
                 * Sample response
                 * [access_token] => <token>
                 * [token_type] => bearer
                 * [expires_in] => 5184000 // 60 days
                 */
                $user_token_longlived = $encoded['access_token'];
            }
        } catch (Exception $e) {
			print $e->getMessage();
        }
        return $user_token_longlived;
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
        if(empty($this->FacebookPageAccessToken)) {
            $this->GetPageAccessToken();
            if(empty($this->FacebookPageAccessToken)) {
                // could not get a facebook page access token
                // even after trying harder
                return false;
            } else {
                // write the value
                $this->CreatePageAccessToken = 0;// avoid circular writes
                $this->write();
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
            'fields'       => "from,message,message_tags,story,story_tags,full_picture,picture,"
                                . "attachments,source,link,object_id,name,caption,description,icon,privacy,"
                                . "type,status_type,created_time,updated_time,shares,is_hidden,is_expired,"
                                . "likes,comments",
            'access_token' => $this->FacebookPageAccessToken,
            'appsecret_proof' => $this->GetAppSecretProof($this->FacebookPageAccessToken)
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
