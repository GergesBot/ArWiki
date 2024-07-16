<?php
namespace Bot\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use WikiConnect\MediawikiApi\Client\Auth\UserAndPassword;
use WikiConnect\MediawikiApi\Client\Action\ActionApi;
use WikiConnect\MediawikiApi\Client\Action\Request\ActionRequest;
use WikiConnect\WikibaseApi\WikibaseFactory;
use WikiConnect\MediawikiApi\DataModel\EditInfo;
use WikiConnect\WikibaseApi\DataModel\DataModelFactory;
use DataValues\Deserializers\DataValueDeserializer;
use DataValues\Serializers\DataValueSerializer;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
class Wikidata {
    private const endpoint = "https://www.wikidata.org/w/api.php";
    private ActionApi $api;
    private static $instance = null;
    private Logger $logger;
    /**
     * Constructor for the Wikidata class.
     *
     * @param UserAndPassword $auth The user and password for the Mediawiki API.
     * @param Client $client The Guzzle HTTP client.
     * @param string|null $endpoint The endpoint of the Wikidata API. Defaults to the
     *                            default endpoint.
     */
    public function __construct(
        UserAndPassword $auth,
        Client $client,
        ?string $endpoint = self::endpoint
    ) {
        // Check for null pointer references.
        if ($auth === null) {
            throw new \InvalidArgumentException('Authentication cannot be null');
        }

        if ($client === null) {
            throw new \InvalidArgumentException('HTTP client cannot be null');
        }
        $day = date("d-M-Y");
        $this->logger = new Logger('Wikidata');
        $this->logger->pushHandler(new StreamHandler( FOLDER_LOGS .'/wikidata/log-' . $day . '.log', Logger::DEBUG));
        // Create a new ActionApi instance with the provided parameters.
        $this->api = new ActionApi(
            $endpoint,
            $auth,
            $client
        );
        // Used method to get the version of the API and Login.
        $this->logger->info('Version: '. $this->api->getVersion());
        if ($this->api->verifyLogin()) {
            $this->logger->info('Login successful');
        } else {
            $this->logger->info('Login failed');
        }
        $this->logger->info('Login: '. $auth->getUsername() );
    }
	/**
	 * Returns an array of data value classes.
	 *
	 * The keys are the names of the data values, and the values are the
	 * corresponding class names.
	 *
	 * @return array<string, string> The data value classes.
	 */
	private function getDataValueClasses(): array {
        return [
            "unknown" => "DataValues\UnknownValue",
            "string" => "DataValues\StringValue",
            "boolean" => "DataValues\BooleanValue",
            "number" => "DataValues\NumberValue",
            "globecoordinate" => "DataValues\Geo\Values\GlobeCoordinateValue",
            "monolingualtext" => "DataValues\MonolingualTextValue",
            "multilingualtext" => "DataValues\MultilingualTextValue",
            "quantity" => "DataValues\QuantityValue",
            "time" => "DataValues\TimeValue",
            "wikibase-entityid" => "Wikibase\DataModel\Entity\EntityIdValue",
        ];
	}
	/**
	 * Returns a factory for creating Wikibase objects.
	 *
	 * @return WikibaseFactory The factory for creating Wikibase objects.
	 */
	public function getFactory(): WikibaseFactory {
        // Create a DataModelFactory with a DataValueDeserializer and a DataValueSerializer.
        // The DataValueDeserializer is initialized with the data value classes.
        $dataModelFactory = new DataModelFactory(
            new DataValueDeserializer($this->getDataValueClasses()),
            new DataValueSerializer()
        );

        // Create a WikibaseFactory with the action API and the DataModelFactory.
        return new WikibaseFactory(
            $this->api,
            $dataModelFactory
        );
	}
  
    /**
     * Returns the instance of the Wikidata class.
     *
     * @return Wikidata The instance of the Wikidata class.
     * @throws \InvalidArgumentException When the Wikidata class has not been initialized.
     */
    public static function getInstance(): Wikidata {

        // Check if the Wikidata class has been initialized.
        if (!isset(self::$instance)) {
            throw new \InvalidArgumentException('Wikidata class not initialized');
        }
        return self::$instance;
    }

    /**
     * Initializes the Wikidata class.
     *
     * @param UserAndPassword $auth The user and password for the Mediawiki API.
     * @param Client $client The Guzzle HTTP client.
     * @param string|null $endpoint The endpoint of the Wikidata API. Defaults to the
     *                            default endpoint.
     */
    public static function initialize(UserAndPassword $auth, Client $client, ?string $endpoint = self::endpoint): void {
        // Set the instance of the Wikidata class.
        self::$instance = new Wikidata($auth, $client, $endpoint);
    }
}