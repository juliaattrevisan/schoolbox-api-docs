<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\TestPlatform;

use Alaress\Web\Controller\Base;
use OAT\Library\Lti1p3Core\Message\Launch\Builder\PlatformOriginatingLaunchBuilder;
use OAT\Library\Lti1p3Core\Message\LtiMessageInterface;
use OAT\Library\Lti1p3Core\Platform\Platform;
use OAT\Library\Lti1p3Core\Registration\Registration;
use OAT\Library\Lti1p3Core\Security\Key\KeyChainFactory;
use OAT\Library\Lti1p3Core\Tool\Tool;
use Schoolbox\Url\Factory;

class Controller extends Base
{
    /**
     * Index for test platform.
     *
     * @api GET /lti-test-platform
     * @since 21.1.0
     */
    public function getIndex(): void
    {
//        // An example config of https://github.com/IMSGlobal/lti-1-3-php-example-tool
//        $exampleToolConfig = [
//            "http://192.168.1.204:9001" => [
//                'client_id'        => (new Factory($this->getConfigLoader()))->buildFullyQualified()->toString(),
//                'auth_login_url'   => 'https://vagrant.dev.schoolbox.cloud/lti-test-platform/oidc-login',
//                'auth_token_url'   => 'https://vagrant.dev.schoolbox.cloud/lti-test-platform/auth-token',
//                'key_set_url'      => 'https://vagrant.dev.schoolbox.cloud/lti-test-platform/key-set',
//                'private_key_file' => '/private.key',
//                "deployment"       => ['test'],
//            ],
//        ];

        // Configure security keys
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-security-keys
        $keyChainFactory = new KeyChainFactory();
        $sharedPublicKey = <<<PUBLICKEY
            -----BEGIN PUBLIC KEY-----
            MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAyZXlfd5yqChtTH91N76V
            okquRu2r1EwNDUjA0GAygrPzCpPbYokasxzs+60Do/lyTIgd7nRzudAzHnujIPr8
            GOPIlPlOKT8HuL7xQEN6gmUtz33iDhK97zK7zOFEmvS8kYPwFAjQ03YKv+3T9b/D
            brBZWy2Vx4Wuxf6mZBggKQfwHUuJxXDv79NenZarUtC5iFEhJ85ovwjW7yMkcflh
            Ugkf1o/GIR5RKoNPttMXhKYZ4hTlLglMm1FgRR63pvYoy9Eq644a9x2mbGelO3Hn
            GbkaFo0HxiKbFW1vplHzixYCyjc15pvtBxw/x26p8+lNthuxzaX5HaFMPGs10rRP
            LwIDAQAB
            -----END PUBLIC KEY-----
            PUBLICKEY;
        $sharedPrivateKey = <<<PRIVATEKEY
            -----BEGIN RSA PRIVATE KEY-----
            MIIEowIBAAKCAQEAyZXlfd5yqChtTH91N76VokquRu2r1EwNDUjA0GAygrPzCpPb
            Yokasxzs+60Do/lyTIgd7nRzudAzHnujIPr8GOPIlPlOKT8HuL7xQEN6gmUtz33i
            DhK97zK7zOFEmvS8kYPwFAjQ03YKv+3T9b/DbrBZWy2Vx4Wuxf6mZBggKQfwHUuJ
            xXDv79NenZarUtC5iFEhJ85ovwjW7yMkcflhUgkf1o/GIR5RKoNPttMXhKYZ4hTl
            LglMm1FgRR63pvYoy9Eq644a9x2mbGelO3HnGbkaFo0HxiKbFW1vplHzixYCyjc1
            5pvtBxw/x26p8+lNthuxzaX5HaFMPGs10rRPLwIDAQABAoIBAEFrF7KlQeg4VmAA
            7PVOMBkeyzfRYXXfyEyLU6dL0JiB9Vl1ajir7bI6rN1v5EKObP1RHwWHXRbr7ery
            jJnjWXRqV2mhDjBseAEIVhpYy6UNWLSBUZ7njiPV/wQaBr2Stq8ydfLKeZpmME7g
            y2xw8uc064qfXIAHbhRq82yAN8+YY7CUKBvJquiiRVDffvbYWhrS4fIIsBNywoBZ
            PGoKUhE9RlR5nRyWvQmeKsBmM9bbzEIW4A5GHO5+6Fb2g1cbLx1poCZRta2oE/JJ
            qusChTZMXdGmEapT8w+sLqyZYtyPn+W8MuX5N9oZppYWIzUDlxHn6yqQPOj98aKu
            rsIu+aECgYEA+x047lp1C3R5dByfk9JwxTayTgfyrnCElV60+KVYxwJkZniMkBk7
            ncy0WtcOBQiwNYLCHMpx9IZkSlWbW0C3e2CgGmLahpi8yhBOzPiUinLY/V5xzbxK
            ojTkf3pYZo1viJG9MTUI1xEr9anZHM9GmqVnk8gJdi2a64wXGyvXxiUCgYEAzYH5
            8oRuvWgT+4KXql8EUX57YQEDIpeqp4DJHMk5X5OWYZ8by5kyRNDLSvqf5kWnkRpK
            VMXuKdpVJXb3OWBvzymzxB/pBfTFFaBybsMQZUKpXfucRSfeQ0T6x1VVLozb6mNH
            T7q4nms1LiUidp7M09XtfwJYMlLu/prZ1yN+jcMCgYEA78e4nAiuKNEARDosumdA
            nWAcJFx8g8sXHtY/MgoY2nbTTfGgLtyZS5WzReaCEZZ5Q69nETzSUW6eh0h1P05t
            pZbfajKofcuMwdmOlTRsCkOeJVmwi4ZXMcoVwhAeJ1a4gIzBPiJpHYvdEQgtM9BM
            l9CHNdrWBg2IF5E+YwibUi0CgYBMKtpa2l648LRHSbWwvZq6IajU4S0qSxBDGOZx
            Ntt+4xKfh/sjUNpiywgt+An/rN1YWGgoV1vYQ0W/pwImT+ng3FH2ZOVXActyIo+H
            IeEXxsdDQBhBQW+Neyl/a88we6CelME/ebMndBC306ecU2sTMHzf0BykOjy+POJ0
            bIFhRQKBgBjfK/s6mbif311d4kEzT0aGIPK0STfD5FKDCazdZ3F+/aS8iorEVGou
            T7EwvdJvb13N9CpQGvN1nbSUc+rI3MY/Fa0m4FxE1A0FnS3AAVISgY3M4k2pjbIa
            3LfQHtOPDKX0gaYFqf99w1Xwtib4E5PFaRcdUuuc1o8Xv315uh8p
            -----END RSA PRIVATE KEY-----
            PRIVATEKEY;


        // Tool keychain needs only the public key: I don't know its private key
        $toolKeyChain = $keyChainFactory->create(
            '1',
            'test',
            $sharedPublicKey,
        );

        // OTOH: I am the platform, I need to know my own private key
        $platformKeyChain = $keyChainFactory->create(
            '1',
            'test',
            $sharedPublicKey,
            $sharedPrivateKey,
        );

        // Configure a platform
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-a-platform
        $platform = new Platform(
            'schoolboxVagrantPlatform',
            'Schoolbox [vagrant]',
            (new Factory($this->getConfigLoader()))->buildFullyQualified()->setScheme('https')->toString(),
            'schoolboxVagrantPlatform',
        );

        // Configure a tool
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-a-tool
        $tool = new Tool(
            'devkitTool',
            'LTI 1.3 DevKit (as tool)',
            'http://devkit-lti1p3.localhost/tool',
            'http://devkit-lti1p3.localhost/lti1p3/oidc/initiation',
            'http://devkit-lti1p3.localhost/tool/launch',
            'http://devkit-lti1p3.localhost/tool/launch'
        );

        // Configure a registration
        // https://oat-sa.github.io/doc-lti1p3/libraries/lib-lti1p3-core/doc/quickstart/configuration/#configure-a-registration
        // Need to set this up in the devkit
        $registration = new Registration(
            'schoolboxVagrant',
            'schoolboxVagrantRegistration',
            $platform,
            $tool,
            ['deploymentId3'],
            $platformKeyChain,
            $toolKeyChain,
        );


        // I have a registration: I may now create a message
        $builder = new PlatformOriginatingLaunchBuilder();
        $message = $builder->buildPlatformOriginatingLaunch(
            $registration,
            LtiMessageInterface::LTI_MESSAGE_TYPE_RESOURCE_LINK_REQUEST,
            'http://devkit-lti1p3.localhost/tool/launch',
            'loginHint',
        );

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@ltiTestPlatform/index.twig',
                [
                    'exampleToolConfig' => $exampleToolConfig ?? [],
                    'messageUrl'        => $message->toUrl(),
                ]
            )
        );
    }
}
