<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\LTI\Provider;

use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

/**
 * An LTI provider.
 *
 * As of LTI 1.3, this is known as a tool.
 */
class Model implements ISoftDeletable
{
    use Id;
    use DeletedAtDate;
    use CreatedAtDate;
    use UpdatedAtDate;

    /**
     * LTI version 1.1
     */
    public const LTI_VERSION_1_1 = '1.1';

    /**
     * LTI version 1.3
     */
    public const LTI_VERSION_1_3 = '1.3';

    /**
     * Available LTI versions
     */
    public const LTI_VERSIONS = [self::LTI_VERSION_1_1, self::LTI_VERSION_1_3];

    /**
     * Target: iframe
     */
    public const TARGET_IFRAME = 'iframe';

    /**
     * Target: new window target
     */
    public const TARGET_WINDOW = 'window';

    /**
     * Available targets
     */
    public const TARGETS = [self::TARGET_IFRAME, self::TARGET_WINDOW];

    /**
     * DB representation of basic launch type.
     *
     * @var string
     */
    public const LAUNCH_TYPE_BASIC = 'LTI_LAUNCH_TYPE_BASIC';

    /**
     * DB representation of picker launch type.
     *
     * @var string
     */
    public const LAUNCH_TYPE_PICKER = 'LTI_LAUNCH_TYPE_PICKER';

    /**
     * Available launch types
     */
    public const LAUNCH_TYPES = [self::LAUNCH_TYPE_BASIC, self::LAUNCH_TYPE_PICKER];

    /**
     * The name of this tool within Schoolbox.
     */
    private string $name;

    /**
     * Which LTI version does this tool use?
     *
     * Must be in `self::LTI_VERSIONS`.
     */
    private string $ltiVersion;

    /**
     * The tool's deployment ID.
     *
     * This will be generated on creation of this tool, and should be considered
     * unique and immutable: it will be used by Schoolbox to identify its
     * deployment to this tool when it is invoked.
     */
    private string $deploymentId = '';

    /**
     * The tool's launch target: where will a resource link launched from this
     * tool be loaded in?
     *
     * Must be one of the values in `self::TARGETS`.
     */
    private string $target = self::TARGET_IFRAME;

    /**
     * LTI 1.3: the client ID of this tool.
     *
     * This will be provided by the tool, and will be:
     * * the `aud` claim in any JWTs sent from the tool to Schoolbox
     *
     * These claims must match the tool's client ID in order for JWTs to verify.
     */
    private ?string $clientId;

    /**
     * LTI 1.3: the audience of this tool.
     *
     * This will be provided by the tool, but appears to be unused on the
     * platform side; it is a candidate for removal.
     */
    private ?string $audience;

    /**
     * LTI 1.3: the OIDC initiation URL of this tool, required for
     * authentication of the Schoolbox user into the tool.
     */
    private ?string $oidcInitiationUrl;

    /**
     * LTI 1.3: the default launch URL of this tool, will be used as the default
     * target link URL for resource link launch requests.
     *
     * Might be optional? If this tool is a Basic launch type, then this is the
     * only relevant URL.
     */
    private ?string $launchUrl;

    /**
     * LTI 1.3: the deep linking URL of this tool, will be used as the launch
     * URL for deep linking requests.
     *
     * (Deep linking requests will return a URL which may be used in a resource
     *  link launch request.)
     *
     * Might be optional? If this tool is a Picker launch type, then this is the
     * only relevant URL.
     */
    private ?string $deepLinkingUrl;

    /**
     * LTI 1.3: the JSON Web Key Set URL of this tool, will be used to determine
     * which key to use to sign JWTs.
     *
     * Either this or the public key must be provided; the public key will take
     * precedence if it is provided.
     */
    private ?string $jwksUrl;

    /**
     * LTI 1.3: the public key of this tool, if provided this key will be used
     * to sign JWTs.
     *
     * Either this or the JSON Web Key Set URL must be provided; this will take
     * precedence if it is provided.
     */
    private ?string $publicKey;

    /**
     * LTI 1.3: is this tool usable within the "Embed Learning Tool" component?
     *
     * This is irrelevant for 1.1, because 1.1 can _only_ be used on components.
     */
    private ?bool $availableForComponent;

    /**
     * LTI 1.3: is this tool usable within third-party assessments?
     *
     * This is irrelevant for 1.1, because 1.1 cannot be used on assessments.
     */
    private ?bool $availableForAssessment;

    /**
     * LTI 1.3: any valid redirect URLs for this provider.
     *
     * @var GenericCollection<RedirectUrl\Model>
     */
    private GenericCollection $redirectUrls;

    /**
     * LTI 1.1: likely intended to store custom fields as defined within the XML
     * configuration: currently unused.
     */
    private ?string $custom;

    /**
     * LTI 1.1: the shared key used for oAuth 1 requests between the tool and
     * Schoolbox.
     */
    private ?string $key;

    /**
     * LTI 1.1: the shared secret used for oAuth 1 requests between the tool and
     * Schoolbox.
     */
    private ?string $secret;

    /**
     * LTI 1.1: the launch URL determined from the XML configuration.
     */
    private ?string $url;

    /**
     * LTI 1.1: a URL at which the XML configuration is available.
     *
     * The configuration will be in Common Cartridge format
     * (@see https://www.imsglobal.org/activity/common-cartridge)
     */
    private ?string $xml;

    /**
     * LTI 1.1: the icon determined from the XML configuration: currently
     * unused.
     */
    private ?string $icon;

    /**
     * LTI 1.1: the launch type, one of the `self::LAUNCH_TYPE_*` constants.
     */
    private ?string $launchType;

    /**
     * Constructor: creates empty redirect URL list.
     */
    public function __construct()
    {
        $this->redirectUrls = new GenericCollection(RedirectUrl\Model::class);
    }

    /**
     * Is this an LTI 1.1 tool?
     *
     * @return bool
     */
    public function isLtiVersion11(): bool
    {
        return $this->ltiVersion === self::LTI_VERSION_1_1;
    }

    /**
     * Is this an LTI 1.3 tool?
     *
     * @return bool
     */
    public function isLtiVersion13(): bool
    {
        return $this->ltiVersion === self::LTI_VERSION_1_3;
    }

    /**
     * Is this tool's launch target an iframe?
     *
     * @return bool
     */
    public function isTargetIframe(): bool
    {
        return $this->target === self::TARGET_IFRAME;
    }

    /**
     * Is this tool's launch target a new tab/window?
     *
     * @return bool
     */
    public function isTargetWindow(): bool
    {
        return $this->target === self::TARGET_WINDOW;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getLtiVersion(): string
    {
        return $this->ltiVersion;
    }

    /**
     * @param string $ltiVersion
     * @return $this
     */
    public function setLtiVersion(string $ltiVersion): self
    {
        $this->ltiVersion = $ltiVersion;
        return $this;
    }

    /**
     * @return string
     */
    public function getDeploymentId(): string
    {
        return $this->deploymentId;
    }

    /**
     * @param string $deploymentId
     * @return $this
     */
    public function setDeploymentId(string $deploymentId): self
    {
        $this->deploymentId = $deploymentId;
        return $this;
    }

    /**
     * @return string
     */
    public function getTarget(): string
    {
        return $this->target;
    }

    /**
     * @param string $target
     * @return $this
     */
    public function setTarget(string $target): self
    {
        $this->target = $target;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    /**
     * @param string|null $clientId
     * @return $this
     */
    public function setClientId(?string $clientId): self
    {
        $this->clientId = $clientId;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getAudience(): ?string
    {
        return $this->audience;
    }

    /**
     * @param string|null $audience
     * @return $this
     */
    public function setAudience(?string $audience): self
    {
        $this->audience = $audience;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getOidcInitiationUrl(): ?string
    {
        return $this->oidcInitiationUrl;
    }

    /**
     * @param string|null $oidcInitiationUrl
     * @return $this
     */
    public function setOidcInitiationUrl(?string $oidcInitiationUrl): self
    {
        $this->oidcInitiationUrl = $oidcInitiationUrl;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLaunchUrl(): ?string
    {
        return $this->launchUrl;
    }

    /**
     * @param string|null $launchUrl
     * @return $this
     */
    public function setLaunchUrl(?string $launchUrl): self
    {
        $this->launchUrl = $launchUrl;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDeepLinkingUrl(): ?string
    {
        return $this->deepLinkingUrl;
    }

    /**
     * @param string|null $deepLinkingUrl
     * @return $this
     */
    public function setDeepLinkingUrl(?string $deepLinkingUrl): self
    {
        $this->deepLinkingUrl = $deepLinkingUrl;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getJwksUrl(): ?string
    {
        return $this->jwksUrl;
    }

    /**
     * @param string|null $jwksUrl
     * @return $this
     */
    public function setJwksUrl(?string $jwksUrl): self
    {
        $this->jwksUrl = $jwksUrl;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }

    /**
     * @param string|null $publicKey
     * @return $this
     */
    public function setPublicKey(?string $publicKey): self
    {
        $this->publicKey = $publicKey;
        return $this;
    }

    /**
     * @return bool|null
     */
    public function getAvailableForComponent(): ?bool
    {
        return $this->availableForComponent;
    }

    /**
     * @param bool|null $availableForComponent
     * @return $this
     */
    public function setAvailableForComponent(?bool $availableForComponent): self
    {
        $this->availableForComponent = $availableForComponent;
        return $this;
    }

    /**
     * @return bool|null
     */
    public function getAvailableForAssessment(): ?bool
    {
        return $this->availableForAssessment;
    }

    /**
     * @param bool|null $availableForAssessment
     * @return $this
     */
    public function setAvailableForAssessment(?bool $availableForAssessment): self
    {
        $this->availableForAssessment = $availableForAssessment;
        return $this;
    }

    /**
     * @return GenericCollection<RedirectUrl\Model>
     */
    public function getRedirectUrls(): GenericCollection
    {
        return $this->redirectUrls;
    }

    /**
     * @param GenericCollection<RedirectUrl\Model> $redirectUrls
     * @return $this
     */
    public function setRedirectUrls(GenericCollection $redirectUrls): self
    {
        $this->redirectUrls = $redirectUrls;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getCustom(): ?string
    {
        return $this->custom;
    }

    /**
     * @param string|null $custom
     * @return $this
     */
    public function setCustom(?string $custom): self
    {
        $this->custom = $custom;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getKey(): ?string
    {
        return $this->key;
    }

    /**
     * @param string|null $key
     * @return $this
     */
    public function setKey(?string $key): self
    {
        $this->key = $key;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSecret(): ?string
    {
        return $this->secret;
    }

    /**
     * @param string|null $secret
     * @return $this
     */
    public function setSecret(?string $secret): self
    {
        $this->secret = $secret;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @param string|null $url
     * @return $this
     */
    public function setUrl(?string $url): self
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getXml(): ?string
    {
        return $this->xml;
    }

    /**
     * @param string|null $xml
     * @return $this
     */
    public function setXml(?string $xml): self
    {
        $this->xml = $xml;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * @param string|null $icon
     * @return $this
     */
    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLaunchType(): ?string
    {
        return $this->launchType;
    }

    /**
     * @param string|null $launchType
     * @return $this
     */
    public function setLaunchType(?string $launchType): self
    {
        $this->launchType = $launchType;
        return $this;
    }
}
