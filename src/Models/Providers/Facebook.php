<?php
namespace SilverstripeSocialFeed\Provider;
use Silverstripe\Forms\LiteralField;
use SilverStripe\Forms\CheckboxField;
use Silverstripe\Forms\DropdownField;
use Silverstripe\Forms\TextareaField;
use Silverstripe\Forms\RequiredFields;
use SilverStripe\ORM\FieldType\DBField;
use League\OAuth2\Client\Provider\Facebook;
use GuzzleHttp\Client as GuzzleHttpClient;
use Exception;
use DateTime;

class FacebookProvider extends SocialFeedProvider implements ProviderInterface
{

	private static $singular_name = 'Facebook Provider via Graph API';
    private static $plural_name = 'Facebook Providers via Graph API';

    private static $description = 'Aggregate posts from a Facebook account';

    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'SocialFeedProviderFacebook';

    private static $db = [
        'FacebookType' => 'Int',
        'FacebookPageID' => 'Varchar(100)',
        'FacebookAppID' => 'Varchar(400)',
        'FacebookAppSecret' => 'Varchar(400)',
        // initial user access token
        'FacebookUserAccessToken' => 'Text',
        'FacebookPageAccessToken' => 'Text',

        'FacebookUserAccessTokenExpires' => 'DBDatetime',
        'FacebookPageAccessTokenCreated' => 'DBDatetime',
        'FacebookPageAccessTokenExpires' => 'DBDatetime',
    ];

    private static $summary_fields = [
        'FacebookPageID'
    ];

    const POSTS_AND_COMMENTS = 0;
    const POSTS_ONLY = 1;

    private static $facebook_types = [
        self::POSTS_AND_COMMENTS => 'Page Posts and Comments',
        self::POSTS_ONLY          => 'Page Posts Only'
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldToTab('Root.Main',
            LiteralField::create(
                'FacebookHelpInformation',
                '<div class="message">'
                . '<h4>'. _t('SocialFeed.FacebookSetup', 'Facebook feed set-up instructions') . '</h4>'
                . '<p>' . _t('SocialFeed.FacebookSetupSubtitle', 'To access a page feed, you must have a page access token linked to target page') . '</p>'
                . '<ol>'
                . '<li>'. _t('SocialFeed.FacebookCopyPageID', 'Copy the target Facebook Page ID or Page Vanity Name to the \'Facebook Page ID\' field') . '</li>'
                . '<li>'. _t('SocialFeed.FacebookCreateApp', 'Create a <a href="https://developers.facebook.com/apps" target="_blank">Facebook App</a>') . '</li>'
                . '<li>'. _t('SocialFeed.FacebookCompleteAppFields', 'Complete all relevant Facebook App fields and get the AppID and App Secret, copy them to the relevant fields here.</a>') . '</li>'
                . '<li>'. _t('SocialFeed.FacebookGetUserToken', 'Get an admin for the relevant Facebook page to get a short lived user token from <a href="https://developers.facebook.com/tools/explorer/">the Graph API Explorer page</a>. Ensure that the correct application name is selected.') . '</li>'
                . '<li>'. _t('SocialFeed.FacebookEnterUserToken', 'Add the user token to the \'Facebook User Access Token\' field here. Once the form is saved, a long-lived user access token will be used to get a page access token') . "</li>"
                . '</ol>'
                . '</div>'
            ),
            'Label'
        );
        $fields->replaceField('FacebookType',
                    DropdownField::create('FacebookType', 'Facebook Type', $this->config()->facebook_types)
                        ->setDescription(
                            _t('SocialFeed.FacebookFeedType', 'Which type of feed would you like to retrieve?')
                        )
        );

        $fields->dataFieldByName('FacebookPageID')
                ->setDescription(
                    _t('SocialFeed.FacebookPageID', 'This value can either be the page vanity name or the actual page id')
                );

        $fields->dataFieldByName('FacebookAppID')
                        ->setDescription(
                            _t('SocialFeed.FacebookAppID', "This value is provided in the App screen on the Facebook Developers site")
                        );

        $fields->dataFieldByName('FacebookAppSecret')
                        ->setDescription(
                            _t('SocialFeed.FacebookAppSecret', "This value is provided in the App screen on the Facebook Developers site")
                        );

        $fields->dataFieldByName('FacebookPageAccessToken')
                        ->setDescription(
                            _t('SocialFeed.FacebookPageTokenVerification',
                                "You can verify this value at the access token debugger: https://developers.facebook.com/tools/debug/accesstoken/")
                            );

        $fields->dataFieldByName('FacebookUserAccessToken')
                ->setDescription(
                    _t('SocialFeed.FacebookUserTokenHelp',
                    'A short lived user access token, created by an admin for the page in question. See documentation for details on how to create this.')
                    );

        if($this->FacebookPageAccessToken) {
            if($this->FacebookUserAccessTokenExpires) {
                $fields->dataFieldByName('FacebookUserAccessTokenExpires')
                    ->setTitle(_t('SocialFeed.FacebookUserTokenExpiry', 'User Access Token Expiry') )
                    ->setDescription('UTC');
            } else {
                $fields->dataFieldByName('FacebookUserAccessTokenExpires')
                    ->setTitle(_t('SocialFeed.FacebookUserTokenExpiry', 'User Access Token Expiry') )
                    ->setDescription( _t('SocialFeed.NotSet', 'Not Set') );
            }
            $fields->makeFieldReadonly( $fields->dataFieldByName('FacebookUserAccessTokenExpires'));
        } else {
            $fields->removeByName('FacebookUserAccessTokenExpires');
        }
        if($this->FacebookPageAccessToken) {
            if($this->FacebookPageAccessTokenExpires) {
                $fields->dataFieldByName('FacebookPageAccessTokenExpires')
                    ->setTitle(_t('SocialFeed.FacebookPageTokenExpiry', 'Page Access Token Expiry') )
                    ->setDescription('UTC');
            } else {
                $fields->dataFieldByName('FacebookPageAccessTokenExpires')
                    ->setTitle(_t('SocialFeed.FacebookPageTokenExpiry', 'Page Access Token Expiry') )
                    ->setDescription( _t('SocialFeed.NeverExpires', 'Never expires') );
            }
            $fields->makeFieldReadonly( $fields->dataFieldByName('FacebookPageAccessTokenExpires'));

            if($this->FacebookPageAccessTokenCreated) {
                $fields->dataFieldByName('FacebookPageAccessTokenCreated')
                    ->setTitle(_t('SocialFeed.FacebookPageTokenCreation', 'Page Access Token Creation') )
                    ->setDescription('UTC');
            } else {
                $fields->dataFieldByName('FacebookPageAccessTokenCreated')
                    ->setTitle(_t('SocialFeed.FacebookPageTokenCreation', 'Page Access Token Creation') )
                    ->setDescription( _t('SocialFeed.NeverExpires', 'Never expires') );
            }
            $fields->makeFieldReadonly( $fields->dataFieldByName('FacebookPageAccessTokenCreated'));
        } else {
            $fields->removeByName('FacebookPageAccessTokenExpires');
            $fields->removeByName('FacebookPageAccessTokenCreated');
        }

        // allow to force recreate the page access token
        $fields->addFieldToTab('Root.Main',
            CheckboxField::create(
                'CreatePageAccessToken',
                _t('SocialFeed.FacebookCreateNewPageToken', 'Create a new page access token')
            ),
            'FacebookPageAccessToken'
        );

        if($this->FacebookPageAccessToken && $this->FacebookUserAccessToken) {
            $response = $this->debugToken();
            $fields->addFieldToTab('Root.Main',
                TextareaField::create(
                    'PageAccessTokenDebug',
                    _t('SocialFeed.FacebookPageTokenDebug', 'Page Access Token Debug'),
                    $response
                )
            );
            $fields->makeFieldReadonly( $fields->dataFieldByName('PageAccessTokenDebug'));
        }

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
        if($this->FacebookAppID && $this->FacebookAppSecret && $this->FacebookUserAccessToken) {
            if($this->CreatePageAccessToken == 1 || empty($this->FacebookPageAccessToken)) {
                $this->GetPageAccessToken();
            }
        }
    }

    /**
     * Get a page access token, save it and the expiry returned
     */
    protected function GetPageAccessToken() {
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
        throw new Exception("Facebook: failed to get a page access token");
    }

    protected function GetAppSecretProof($token) {
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
        $error = "";
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
                return $page_access_token;
            }
            $error = "Facebook: no access_token in page access token request response";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
        throw new Exception($error);
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
        $error = "";
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
                if(!empty($encoded['expires_in']) && is_numeric($encoded['expires_in'])) {
                    try {
                        $date = gmdate("Y-m-d H:i:s", $encoded['expires_in']);
                        $this->FacebookUserAccessTokenExpires = $date;
                    } catch (Exception $e) {}
                }
                return $user_token_longlived;
            }
            $error = "Facebook: no access_token in exchange token request response";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
        throw new Exception($error);
    }



    /**
     * Debug the page access token to get expiry date and such
     */
    private function debugToken() {
        if(!$this->FacebookPageAccessToken || !$this->FacebookUserAccessToken) {
            return false;
        }
        $body = "";
        try {
            $appsecret_proof = $this->GetAppSecretProof($this->FacebookUserAccessToken);
            $url = "https://graph.facebook.com"
                    . "/debug_token"
                    . "?input_token={$this->FacebookPageAccessToken}"
                    . "&appsecret_proof={$appsecret_proof}"
                    . "&access_token={$this->FacebookUserAccessToken}";
            $error = "";
            $client = new GuzzleHttpClient();
            $options = [];
            $response = $client->request("GET", $url, $options);
            $body = $response->getBody()->getContents();
            $encoded = json_decode($body, true);

            $this->FacebookPageAccessTokenExpires = null;
            $this->FacebookPageAccessTokenCreated = null;


            $data = "";
            if(!empty($encoded['data'])) {
                $data = json_encode($encoded['data']);
                if(isset($encoded['data']['expires_at'])) {
                    if($encoded['data']['expires_at'] == 0) {
                        $this->FacebookPageAccessTokenExpires = NULL;
                    } else {
                        try {
                            $date = gmdate("Y-m-d H:i:s", $encoded['data']['expires_at']);
                            $this->FacebookPageAccessTokenExpires = $date;
                        } catch (Exception $e) {}
                    }
                }

                if(isset($encoded['data']['issued_at'])) {
                    try {
                        $date = gmdate("Y-m-d H:i:s", $encoded['data']['issued_at']);
                        $this->FacebookPageAccessTokenCreated = $date;
                    } catch (Exception $e) {}
                }
            }

            $this->UnsetWriteModifiers();
            $this->write();

            /*
            Example:
            {
                "data": {
                    "app_id": 000000000000000,
                    "application": "Social Cafe",
                    "expires_at": 1352419328,
                    "is_valid": true,
                    "issued_at": 1347235328,
                    "scopes": [
                        "email",
                        "user_location"
                    ],
                    "user_id": 1207059
                }
            }
            */

            if(!$data) {
                $data = "Facebook: no data in debug token response";
            }
        } catch (Exception $e) {
            $data = $e->getMessage();
        }
        return $data;
    }

    /**
     * Return the type of provider
     *
     * @return string
     */
    public function getType()
    {
        return parent::PROVIDER_FACEBOOK;
    }

    public function getFeedUncached()
    {
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

    protected function UnsetWriteModifiers() {
        parent::UnsetWriteModifiers();
        $this->CreatePageAccessToken = 0;
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
