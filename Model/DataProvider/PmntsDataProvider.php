<?php
declare(strict_types=1);

namespace PMNTS\Gateway\Model\DataProvider;

use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\QuoteGraphQl\Model\Cart\Payment\AdditionalDataProviderInterface;

class PmntsDataProvider implements AdditionalDataProviderInterface
{

    private const PATH_ADDITIONAL_DATA = 'pmnts_gateway';

    /**
     * Get additional data for pmnts
     *
     * @param array<mixed> $data
     * @return array<mixed>
     * @throws GraphQlInputException
     */
    public function getData(array $data): array
    {
        if (!isset($data[self::PATH_ADDITIONAL_DATA])) {
            throw new GraphQlInputException(
                __('Required parameter "pmnts_gateway" for "payment_method" is missing.')
            );
        }
        return $data[self::PATH_ADDITIONAL_DATA];
    }
}
