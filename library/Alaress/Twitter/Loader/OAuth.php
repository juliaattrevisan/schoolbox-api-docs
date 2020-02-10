<?php
/**
 * Created by Steve Chan.
 *
 * User: Steve
 * Date: 28/07/11
 * Time: 12:25 PM
 *
 * Twitter_Loader_OAuth
 *
 * This loads tweets via the OAuth interface that Twitter brings in on August 31
 * 2011. It encapsulates @abraham's twitteroauth class library found at:
 * https://github.com/abraham/twitteroauth/blob/master/DOCUMENTATION
 *
 * This class encapsulates the tweet loading methodology, returning a key/value
 * array for text (the contents of the tweet) and the date (the date the tweet
 * was published)
 *
 * To change the API resource, use $twitter->getLoader()->setResource('statuses/mentions');
 */
class Alaress_Twitter_Loader_OAuth implements Alaress_Twitter_Loader_Interface
{

    /**
     * REST API method. Defaults to statuses/user_timeline
     * See https://dev.twitter.com/docs/api
     *
     * @var string
     */
    private $resource = 'statuses/user_timeline';

    /**
     * An array of params passed to the twitter connection,
     * typically the search params
     *
     * @var array
     */
    private $params;

    /**
     * @var TwitterOAuth
     */
    private $connection;


    /**
     * Load required classes, instantiate a new connection using the four
     * variables we obtained by running connect.php
     * @param string      $consumer_key
     * @param string      $consumer_secret
     * @param string|null $oauth_token
     * @param string|null $oauth_secret
     */
    public function __construct(string $consumer_key, string $consumer_secret, ?string $oauth_token, ?string $oauth_secret)
    {
        require_once 'oauth/TwitterOAuth.php';
        $this->connection = new TwitterOAuth($consumer_key, $consumer_secret, $oauth_token, $oauth_secret);
    }

    /**
     * Load results. Preprocess them to get rid of unwanted data and to format
     * the return value in a uniform structure.
     *
     * @param null|string $resource
     * @param null|array  $params
     * @return array
     */
    public function load(?string $resource, ?array $params = null): array
    {
        if (null === $resource) {
            $resource = $this->resource;
        }
        if (null === $params) {
            $params = $this->params;
        }
        $result = $this->connection->get($resource, $params);
        $code   = (int)$this->connection->http_code;
        if ($code >= 400) {
            //
            // Log errors somewhere, and return an empty array?
            return [];
        }

        /* Coerce return type to conform to expected data structure
         */
        return $result->statuses ?? [];
    }
}
