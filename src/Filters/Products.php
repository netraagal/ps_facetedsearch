<?php

namespace PrestaShop\Module\FacetedSearch\Filters;

use PrestaShop\Module\FacetedSearch\Product\Search;
use NativeModuleFacetedSearchBundle\Adapter\FacetedSearchAbstract;
use Product;
use Validate;
use Configuration;

class Products
{
    /** @var FacetedSearchAbstract */
    private $facetedSearchAdapter;

    public function __construct(Search $productSearch)
    {
        $this->facetedSearchAdapter = $productSearch->getFacetedSearchAdapter();
    }

    /**
     * Remove products from the product list in case of price postFiltering
     *
     * @param array $matchingProductList
     * @param bool $psLayeredFilterPriceUsetax
     * @param bool $psLayeredFilterPriceRounding
     * @param array $priceFilter
     */
    private function filterPrice(
        &$matchingProductList,
        $psLayeredFilterPriceUsetax,
        $psLayeredFilterPriceRounding,
        $priceFilter
    ) {
        /* for this case, price could be out of range, so we need to compute the real price */
        foreach ($matchingProductList as $key => $product) {
            if (($product['price_min'] < (int) $priceFilter['min'] && $product['price_max'] > (int) $priceFilter['min'])
                || ($product['price_max'] > (int) $priceFilter['max'] && $product['price_min'] < (int) $priceFilter['max'])) {
                $price = Product::getPriceStatic($product['id_product'], $psLayeredFilterPriceUsetax);
                if ($psLayeredFilterPriceRounding) {
                    $price = (int) $price;
                }

                if ($price < $priceFilter['min'] || $price > $priceFilter['max']) {
                    // out of range price, exclude the product
                    unset($matchingProductList[$key]);
                }
            }
        }
    }

    /**
     * Get the products associated with the current filters
     *
     * @param int $productsPerPage
     * @param int $page
     * @param string $orderBy
     * @param string $orderWay
     * @param array $selectedFilters
     *
     * @return array
     */
    public function getProductByFilters(
        $productsPerPage,
        $page,
        $orderBy,
        $orderWay,
        $selectedFilters = []
    ) {
        $this->facetedSearchAdapter->setLimit((int) $productsPerPage, ((int) $page - 1) * $productsPerPage);

        if (!Validate::isOrderBy($orderBy)) {
            $this->facetedSearchAdapter->setOrderField('position');
        } else {
            $this->facetedSearchAdapter->setOrderField($orderBy);
        }

        if (!Validate::isOrderWay($orderWay)) {
            $this->facetedSearchAdapter->setOrderDirection('ASC');
        } else {
            $this->facetedSearchAdapter->setOrderDirection($orderWay);
        }

        $this->facetedSearchAdapter->addGroupBy('id_product');
        if (isset($selectedFilters['price'])) {
            $this->facetedSearchAdapter->addSelectField('id_product');
            $this->facetedSearchAdapter->addSelectField('price');
            $this->facetedSearchAdapter->addSelectField('price_min');
            $this->facetedSearchAdapter->addSelectField('price_max');
        }
        $matchingProductList = $this->facetedSearchAdapter->execute();

        // @TODO: still usefull ?
        //$this->pricePostFiltering($matchingProductList, $selectedFilters);

        $nbrProducts = $this->facetedSearchAdapter->count();

        if ($nbrProducts == 0) {
            $matchingProductList = [];
        }

        return [
            'products' => $matchingProductList,
            'count' => $nbrProducts,
        ];
    }

    /**
     * Post filter product depending on the price and a few extra config variables
     *
     * @param array $matchingProductList
     * @param array $selectedFilters
     */
    private function pricePostFiltering(&$matchingProductList, $selectedFilters)
    {
        if (isset($selectedFilters['price'])) {
            $priceFilter['min'] = (float) ($selectedFilters['price'][0]);
            $priceFilter['max'] = (float) ($selectedFilters['price'][1]);

            static $psLayeredFilterPriceUsetax = null;
            static $psLayeredFilterPriceRounding = null;

            if ($psLayeredFilterPriceUsetax === null) {
                $psLayeredFilterPriceUsetax = Configuration::get('PS_LAYERED_FILTER_PRICE_USETAX');
            }

            if ($psLayeredFilterPriceRounding === null) {
                $psLayeredFilterPriceRounding = Configuration::get('PS_LAYERED_FILTER_PRICE_ROUNDING');
            }

            if ($psLayeredFilterPriceUsetax || $psLayeredFilterPriceRounding) {
                $this->filterPrice($matchingProductList, $psLayeredFilterPriceUsetax, $psLayeredFilterPriceRounding,
                    $priceFilter);
            }
        }
    }
}
