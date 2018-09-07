<?php
namespace SilverstripeSocialFeed\Provider;
use Silverstripe\Forms\LiteralField;
use Silverstripe\Forms\DropdownField;
use Silverstripe\Control\Director;
use Silverstripe\Forms\RequiredFields;
use SilverStripe\ORM\FieldType\DBField;
use League\OAuth2\Client\Provider\Instagram;
use Exception;

class InstagramProvider extends SocialFeedProvider implements ProviderInterface
{

    /**
     * Defines the database table name
     * @var string
     */
    private static $table_name = 'SocialFeedProviderInstagram';

    private static $db = array(
        'ClientID' => 'Varchar(400)',
        'ClientSecret' => 'Varchar(400)',
        'AccessToken' => 'Varchar(400)'
    );

    private static $singular_name = 'Instagram Provider';
    private static $plural_name = 'Instagram Providers';

    private $authBaseURL = 'https://api.instagram.com/oauth/authorize/';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldToTab(
            'Root.Main',
            LiteralField::create(
                'InstgramInstructions',
                '<p class="message">'
                . _t('SocialFeed.InstgramInstructions', 'To get the necessary Instagram API credentials'
                . ' you\'ll need to create an '
                . '<a href="https://www.instagram.com/developer/clients/manage/" target="_blank">Instagram Client.</a>'
                . '<br>You\'ll need to add the following redirect URI '
                . '<code>' . $this->getRedirectUri() . '</code> in the settings for the Instagram App.')
                . '</p>'
            ),
            'Label'
        );

        if ($this->ClientID && $this->ClientSecret) {
            $url = $this->authBaseURL . '?client_id=' . $this->ClientID . '&response_type=code&redirect_uri=' . $this->getRedirectUri() . '?provider_id=' . $this->ID;
            $fields->addFieldToTab(
                'Root.Main',
                LiteralField::create(
                    'InstgramRedirect',
                    '<p class="message"><a href="'. $url . '"><button type="button">'
                    . _t('SocialFeed.InstgramRedirect', 'Authorize App to get Access Token')
                    . '</a></button>'
                ),
                'Label'
            );
        }

        return $fields;
    }

    public function getCMSValidator()
    {
        return new RequiredFields(array('ClientID', 'ClientSecret'));
    }

    /**
     * Construct redirect URI using current class name - used during OAuth flow.
     * @return string
     */
    private function getRedirectUri()
    {

        return Director::absoluteBaseURL() . 'admin/social-feed/' . $this->sanitiseClassName() . '/';
    }

    /**
     * Fetch access token using code, used in the second step of OAuth flow.
     *
     * @param $accessCode
     * @return \League\OAuth2\Client\Token\AccessToken
     */
    public function fetchAccessToken($accessCode)
    {
        $provider = new Instagram([
            'clientId' => $this->ClientID,
            'clientSecret' => $this->ClientSecret,
            'redirectUri' => $this->getRedirectUri() . '?provider_id=' . $this->ID
        ]);

        //TODO: handle token expiry (as of 2016-08-03, Instagram access tokens don't expire.)
        //TODO: save returned user data?
        return $token = $provider->getAccessToken('authorization_code', [
            'code' => $accessCode
        ]);
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
     * Fetch Instagram data for authorized user
     *
     * @return mixed
     */
    public function getFeedUncached()
    {
        $provider = new Instagram([
            'clientId' => $this->ClientID,
            'clientSecret' => $this->ClientSecret,
            'redirectUri' => $this->getRedirectUri() . '?provider_id=' . $this->ID
        ]);

        $request = $provider->getRequest('GET', 'https://api.instagram.com/v1/users/self/media/recent/?access_token=' . $this->AccessToken);
        try {
            $result = $provider->getResponse($request);
        } catch (Exception $e) {
            $errorHelpMessage = '';
            if ($e->getCode() == 400) {
                // "Missing client_id or access_token URL parameter." or "The access_token provided is invalid."
                $cmsLink = Director::absoluteBaseURL().'admin/social-feed/SocialFeedProviderInstagram/EditForm/field/SocialFeedProviderInstagram/item/'.$this->ID.'/edit';
                $errorHelpMessage = ' -- Go here '.$cmsLink.' and click "Authorize App to get Access Token" to restore Instagram feed.';
            }
            // Throw warning as we don't want the whole site to go down if Instagram starts failing.
            // user_error($e->getMessage() . $errorHelpMessage, E_USER_WARNING);
            $result['data'] = array();
        }
        return $result['data'];
    }

    /**
     * @return HTMLText
     */
    public function getPostContent($post, $strip_html = true) {
        $text = isset($post['caption']['text']) ? $post['caption']['text'] : '';
        return parent::processTextContent($text, $strip_html);
    }

    /**
     * Get the creation time from a post
     *
     * @param $post
     * @return mixed
     */
    public function getPostCreated($post)
    {
        return isset($post['created_time']) ? $post['created_time'] : '';
    }

    /**
     * Get the post URL from a post
     *
     * @param $post
     * @return mixed
     */
    public function getPostUrl($post)
    {
        return isset($post['link']) ? $post['link'] : '';
    }

    /**
     * Get the user who created the post
     *
     * @param $post
     * @return mixed
     */
    public function getUserName($post)
    {
        return isset($post['user']['username']) ? $post['user']['username'] : '';
    }

    /**
     * Get the primary image for the post
     *
     * @param $post
     * @return mixed
     */
    public function getImage($post)
    {
        return isset($post['images']['standard_resolution']['url']) ? $post['images']['standard_resolution']['url'] : '';
    }



    /**
     * Twitter's low res version of the feed image ~400w
     */
    public function getImageLowRes($post)
    {
        return isset($post['images']['low_resolution']['url']) ? $post['images']['low_resolution']['url'] : '';
        return $image;
    }

    /**
     * Twitter's thumb version of the feed image ~150w
     */
    public function getImageThumb($post)
    {
        return isset($post['images']['thumb']['url']) ? $post['images']['thumb']['url'] : '';
    }

    public function getPostType($post) {
        return null;
    }
}
