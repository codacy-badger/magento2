<?php declare(strict_types=1);
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Directory\Test\Unit\Helper;

use Magento\Directory\Helper\Data;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Directory\Model\ResourceModel\Country\Collection;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory;
use Magento\Framework\App\Cache\Type\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Constraint\IsIdentical;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DataTest extends TestCase
{
    /**
     * @var Collection|MockObject
     */
    protected $_countryCollection;

    /**
     * @var CollectionFactory|MockObject
     */
    protected $_regionCollection;

    /**
     * @var \Magento\Framework\Json\Helper\Data|MockObject
     */
    protected $jsonHelperMock;

    /**
     * @var Store|MockObject
     */
    protected $_store;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    protected $scopeConfigMock;

    /**
     * @var Data
     */
    protected $_object;

    protected function setUp(): void
    {
        $objectManager = new ObjectManager($this);
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->scopeConfigMock->expects($this->any())->method('isSetFlag')->willReturn(false);
        $context = $this->createMock(Context::class);
        $context->expects($this->any())
            ->method('getScopeConfig')
            ->willReturn($this->scopeConfigMock);

        $configCacheType = $this->createMock(Config::class);

        $this->_countryCollection = $this->createMock(Collection::class);

        $this->_regionCollection = $this->createMock(\Magento\Directory\Model\ResourceModel\Region\Collection::class);
        $regCollectionFactory = $this->createPartialMock(
            CollectionFactory::class,
            ['create']
        );
        $regCollectionFactory->expects(
            $this->any()
        )->method(
            'create'
        )->will(
            $this->returnValue($this->_regionCollection)
        );

        $this->jsonHelperMock = $this->createMock(\Magento\Framework\Json\Helper\Data::class);

        $this->_store = $this->createMock(Store::class);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects($this->any())->method('getStore')->will($this->returnValue($this->_store));

        $currencyFactory = $this->createMock(CurrencyFactory::class);

        $arguments = [
            'context' => $context,
            'configCacheType' => $configCacheType,
            'countryCollection' => $this->_countryCollection,
            'regCollectionFactory' => $regCollectionFactory,
            'jsonHelper' => $this->jsonHelperMock,
            'storeManager' => $storeManager,
            'currencyFactory' => $currencyFactory,
        ];
        $this->_object = $objectManager->getObject(Data::class, $arguments);
    }

    public function testGetRegionJson()
    {
        $countries = [
            new DataObject(['country_id' => 'Country1']),
            new DataObject(['country_id' => 'Country2'])
        ];
        $countryIterator = new \ArrayIterator($countries);
        $this->_countryCollection->expects(
            $this->atLeastOnce()
        )->method(
            'getIterator'
        )->will(
            $this->returnValue($countryIterator)
        );

        $regions = [
            new DataObject(
                ['country_id' => 'Country1', 'region_id' => 'r1', 'code' => 'r1-code', 'name' => 'r1-name']
            ),
            new DataObject(
                ['country_id' => 'Country1', 'region_id' => 'r2', 'code' => 'r2-code', 'name' => 'r2-name']
            ),
            new DataObject(
                ['country_id' => 'Country2', 'region_id' => 'r3', 'code' => 'r3-code', 'name' => 'r3-name']
            )
        ];
        $regionIterator = new \ArrayIterator($regions);

        $this->_regionCollection->expects(
            $this->once()
        )->method(
            'addCountryFilter'
        )->with(
            ['Country1', 'Country2']
        )->will(
            $this->returnSelf()
        );
        $this->_regionCollection->expects($this->once())->method('load');
        $this->_regionCollection->expects(
            $this->once()
        )->method(
            'getIterator'
        )->will(
            $this->returnValue($regionIterator)
        );

        $expectedDataToEncode = [
            'config' => ['show_all_regions' => false, 'regions_required' => []],
            'Country1' => [
                'r1' => ['code' => 'r1-code', 'name' => 'r1-name'],
                'r2' => ['code' => 'r2-code', 'name' => 'r2-name']
            ],
            'Country2' => ['r3' => ['code' => 'r3-code', 'name' => 'r3-name']]
        ];
        $this->jsonHelperMock->expects(
            $this->once()
        )->method(
            'jsonEncode'
        )->with(
            new IsIdentical($expectedDataToEncode)
        )->will(
            $this->returnValue('encoded_json')
        );

        // Test
        $result = $this->_object->getRegionJson();
        $this->assertEquals('encoded_json', $result);
    }

    /**
     * @param string $configValue
     * @param mixed $expected
     * @dataProvider countriesCommaListDataProvider
     */
    public function testGetCountriesWithStatesRequired($configValue, $expected)
    {
        $this->scopeConfigMock->expects(
            $this->once()
        )->method(
            'getValue'
        )->with(
            'general/region/state_required'
        )->will(
            $this->returnValue($configValue)
        );

        $result = $this->_object->getCountriesWithStatesRequired();
        $this->assertEquals($expected, $result);
    }

    /**
     * @param string $configValue
     * @param mixed $expected
     * @dataProvider countriesCommaListDataProvider
     */
    public function testGetCountriesWithOptionalZip($configValue, $expected)
    {
        $this->scopeConfigMock->expects(
            $this->once()
        )->method(
            'getValue'
        )->with(
            'general/country/optional_zip_countries'
        )->will(
            $this->returnValue($configValue)
        );

        $result = $this->_object->getCountriesWithOptionalZip();
        $this->assertEquals($expected, $result);
    }

    /**
     * @return array
     */
    public static function countriesCommaListDataProvider()
    {
        return [
            'empty_list' => ['', []],
            'normal_list' => ['Country1,Country2', ['Country1', 'Country2']]
        ];
    }

    public function testGetDefaultCountry()
    {
        $storeId = 'storeId';
        $country = 'country';

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(
                Data::XML_PATH_DEFAULT_COUNTRY,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )->will($this->returnValue($country));

        $this->assertEquals($country, $this->_object->getDefaultCountry($storeId));
    }

    public function testGetCountryCollection()
    {
        $this->_countryCollection->expects(
            $this->once()
        )->method(
            'isLoaded'
        )->will(
            $this->returnValue(0)
        );

        $store = $this->createMock(Store::class);
        $this->_countryCollection->expects(
            $this->once()
        )->method(
            'loadByStore'
        )->with(
            $store
        );

        $this->_object->getCountryCollection($store);
    }

    /**
     * @param string $topCountriesValue
     * @param array $expectedResult
     * @dataProvider topCountriesDataProvider
     */
    public function testGetTopCountryCodesReturnsParsedConfigurationValue($topCountriesValue, $expectedResult)
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')->with(Data::XML_PATH_TOP_COUNTRIES)
            ->willReturn($topCountriesValue);

        $this->assertEquals($expectedResult, $this->_object->getTopCountryCodes());
    }

    /**
     * @return array
     */
    public function topCountriesDataProvider()
    {
        return [
            [null, []],
            ['', []],
            ['US', ['US']],
            ['US,RU', ['US', 'RU']],
        ];
    }
}
