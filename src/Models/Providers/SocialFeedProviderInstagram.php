<?php
namespace SilverstripeSocialFeed\Provider;
use Silverstripe\Forms\LiteralField;
use SilverStripe\Forms\DropdownField;
use Silverstripe\Control\Director;
use Silverstripe\Forms\RequiredFields;
use SilverStripe\ORM\FieldType\DBField;
use Exception;
use DateTime;

class InstagramProvider extends FacebookProvider implements ProviderInterface
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

        // deprecated fields, emptied on write
        $fields->removeByName('ClientID');
        $fields->removeByName('ClientSecret');
        $fields->removeByName('AccessToken');

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
