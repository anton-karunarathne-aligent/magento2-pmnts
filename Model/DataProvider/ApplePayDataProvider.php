<?php
declare(strict_types=1);

namespace PMNTS\Gateway\Model\DataProvider;

use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\QuoteGraphQl\Model\Cart\Payment\AdditionalDataProviderInterface;

class ApplePayDataProvider implements AdditionalDataProviderInterface
{

    private const PATH_ADDITIONAL_DATA = 'fatzebra_applepay';

    /**
     * Get additional data for Apple Pay
     *
     * @param array $data
     * @return array
     * @throws GraphQlInputException
     */
    public function getData(array $data): array
    {
        if (!isset($data[self::PATH_ADDITIONAL_DATA])) {
            throw new GraphQlInputException(
                __('Required parameter "fatzebra_applepay" for "payment_method" is missing.')
            );
        }
        return $data[self::PATH_ADDITIONAL_DATA];
    }
}
