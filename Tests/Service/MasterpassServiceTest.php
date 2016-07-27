<?php

namespace Hoya\MasterpassBundle\Tests\Service;

use Hoya\MasterpassBundle\Common\Connector;
use Hoya\MasterpassBundle\Tests\BaseWebTestCase;
use Hoya\MasterpassBundle\DTO\RequestTokenResponse;
use Hoya\MasterpassBundle\DTO\Shoppingcart;
use Hoya\MasterpassBundle\DTO\ShoppingcartItem;
use Hoya\MasterpassBundle\DTO\CallbackResponse;
use Hoya\MasterpassBundle\DTO\AccessTokenResponse;
use Hoya\MasterpassBundle\Service\MasterpassService;

/**
 * MasterpassServiceTest.
 *
 * @author Marcos Lazarin
 */
class MasterpassServiceTest extends BaseWebTestCase
{
    const ACCESSTOKEN = 'doAccessToken';
    
    const CHECKOUTDATA = 'doCheckoutData';

    /**
     * @return MasterpassService
     */
    protected function getService()
    {
        return $this->getContainer()->get('hoya_masterpass_service');
    }

    /**
     * Testing service instance.
     */
    public function testInstance()
    {
        $this->assertInstanceOf('\Hoya\MasterpassBundle\Service\MasterpassService', $this->getService());
    }

    /**
     * Testing request token.
     * 
     * @return RequestTokenResponse
     */
    public function testRequestToken()
    {
        $rt = $this->getService()->getRequestToken();

        $this->assertInstanceOf('\Hoya\MasterpassBundle\DTO\RequestTokenResponse', $rt);
        $this->assertGreaterThanOrEqual(40, strlen($rt->requestToken), 'requestToken does not have a valid format');
        $this->assertGreaterThanOrEqual(40, strlen($rt->oAuthSecret), 'oAuthSecret does not have a valid format');
        $this->assertEquals(900, $rt->oAuthExpiresIn, 'oAuthExpiresIn does not have a valid value');

        return $rt;
    }

    /**
     * Testing shoppingcart.
     * 
     * @depends testRequestToken
     */
    public function testShoppingCart(RequestTokenResponse $requestToken)
    {
        $item = new ShoppingcartItem();
        $item->quantity = 1;
        $item->imageUrl = 'https://localhost.com';
        $item->description = 'My test item';
        $item->setAmount(10.00);

        $cart = new Shoppingcart();
        $cart->currency = 'USD';
        $cart->addItem(1, $item);

        $shoppingCartXml = $cart->toXML();

        $cartXml = $this->getService()->postShoppingCartData($requestToken, $shoppingCartXml);

        $this->assertRegExp('<ShoppingCartResponse>', $cartXml, 'Response does not contain ShoppingCartResponse');
        $this->assertRegExp('<OAuthToken>', $cartXml, 'Response does not contain OAuthToken');

        return $cartXml;
    }

    public function testHandleCallback()
    {
        //oauth_token=db5a9e2a443ccf89ed825e6c9c0ef874ebe48097&oauth_verifier=af7bd8e66095828f8cc8c57096583ca848d62793&checkoutId=449087232&checkout_resource_url=https%3A%2F%2Fsandbox.api.mastercard.com%2Fmasterpass%2Fv6%2Fcheckout%2F449087232&mpstatus=success
    }

    /**
     * Test access token return.
     */
    public function testAccessToken()
    {
        $return = 'oauth_token=c7d33d2c6b6b49dc17db786c73a73b3abcadc43a&oauth_token_secret=399e50ba507a0faa27300ecfb50d55390f51f539';

        $connector = $this->getMockConnector($return, self::ACCESSTOKEN);

        $service = new MasterpassService($connector);

        $callback = new CallbackResponse;
        $callback->requestVerifier = '8f55989fa03a6dbe173749d0c495872f4a38d84c';
        $callback->requestToken = '259f063894e0a1ab8996f805bbbeeab535812d6f';

        $accessTokenResponse = $service->getAccessToken($callback);
        
        $this->assertInstanceOf('\Hoya\MasterpassBundle\DTO\AccessTokenResponse', $accessTokenResponse);
        $this->assertEquals('c7d33d2c6b6b49dc17db786c73a73b3abcadc43a', $accessTokenResponse->accessToken, 'accessToken does not have a valid value');
    }

    public function testCheckoutData()
    {
        $return = <<<XML
<Checkout>
   <Card>
      <BrandId>master</BrandId>
      <BrandName>MasterCard</BrandName>
      <AccountNumber>5204740009900022</AccountNumber>
      <BillingAddress>
         <City>Milpitas</City>
         <Country>US</Country>
         <CountrySubdivision>US-CA</CountrySubdivision>
         <Line1>123 S Main St</Line1>
         <PostalCode>95035</PostalCode>
      </BillingAddress>
      <CardHolderName>Joe test</CardHolderName>
      <ExpiryMonth>1</ExpiryMonth>
      <ExpiryYear>2020</ExpiryYear>
   </Card>
   <TransactionId>446156154</TransactionId>
   <Contact>
      <FirstName>Joe</FirstName>
      <LastName>test</LastName>
      <Country>US</Country>
      <EmailAddress>joe.test@example.com</EmailAddress>
      <PhoneNumber>6547531792</PhoneNumber>
   </Contact>
   <ShippingAddress>
      <City>tuscaloosa</City>
      <Country>US</Country>
      <CountrySubdivision>US-AL</CountrySubdivision>
      <Line1>123 main street</Line1>
      <PostalCode>35404</PostalCode>
      <RecipientName>Joe test</RecipientName>
      <RecipientPhoneNumber>6547530000</RecipientPhoneNumber>
   </ShippingAddress>
   <WalletID>101</WalletID>
   <ExtensionPoint>
      <CardVerificationStatus>001</CardVerificationStatus>
   </ExtensionPoint>
</Checkout>
XML;
        $connector = $this->getMockConnector($return, self::CHECKOUTDATA);
        $service = new MasterpassService($connector);
        
        $accessToken = new AccessTokenResponse;
        $accessToken->checkoutResourceUrl = 'https://sandbox.api.mastercard.com/masterpass/v6/checkout/446156154';
        $accessToken->accessToken = 'c7d33d2c6b6b49dc17db786c73a73b3abcadc43a';
        
        $checkoutData = $service->getCheckoutData($accessToken);

        $this->assertRegExp('<Checkout>', $checkoutData, 'Response does not contain Checkout');
        $this->assertRegExp('<TransactionId>', $checkoutData, 'Response does not contain TransactionId');
    }

}
