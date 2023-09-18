<?php

namespace PHP_CodeSniffer\Standards\Iservice\Sniffs\ImportedFiles;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class ChangeClassNamesSniff implements Sniff
{

    public function register()
    {
        return [T_STRING, T_DOUBLE_COLON];
    }

    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $searchReplacePairs = [
            'PluginIserviceCommon' => 'IserviceToolBox',
            'PluginFieldsSuppliercustomfield' => 'PluginFieldsSuppliersuppliercustomfield',
            'PluginFieldsPrintermodelcustomfield' => 'PluginFieldsPrintermodelprintermodelcustomfield',
            'PluginFieldsTicketcustomfield' => 'PluginFieldsTicketticketcustomfield',
            'PluginFieldsPrintercustomfield' => 'PluginFieldsPrinterprintercustomfield',
            'PluginFieldsContractcustomfield' => 'PluginFieldsContractcontractcustomfield',
            'PluginFieldsCartridgeitemcustomfield' => 'PluginFieldsCartridgeitemcartridgeitemcustomfield',
            'PluginFieldsCartridgecustomfield' => 'PluginFieldsCartridgecartridgecustomfield',
        ];

        foreach ($searchReplacePairs as $search => $replace) {
            if ($tokens[$stackPtr]['content'] === $search) {
                $fix = $phpcsFile->addFixableError(
                    'Replace "' . $search . '" with "' . $replace . '"',
                    $stackPtr,
                    'Replace' . $search
                );

                if ($fix) {
                    $phpcsFile->fixer->beginChangeset();
                    $phpcsFile->fixer->replaceToken($stackPtr, $replace);
                    $phpcsFile->fixer->endChangeset();
                }
            }
        }

    }

}
