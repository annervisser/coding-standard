<?php declare(strict_types = 1);

namespace SlevomatCodingStandard\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\FunctionHelper;
use SlevomatCodingStandard\Helpers\TokenHelper;
use function assert;
use function in_array;
use function is_bool;
use function is_int;
use function is_string;
use function preg_replace;
use function preg_replace_callback;
use function rtrim;
use function sprintf;
use function str_repeat;
use function str_replace;
use function strlen;
use const T_FUNCTION;
use const T_OPEN_CURLY_BRACKET;
use const T_OPEN_TAG;
use const T_SEMICOLON;

class RequireSingleLineMethodDeclarationSniff implements Sniff
{

	public const CODE_UNNECESSARY_MULTI_LINE_METHOD = 'UnnecessaryMultiLineMethod';

	/** @var int */
	public $maxLineLength = 120;

	/**
	 * @return array<int, (int|string)>
	 */
	public function register(): array
	{
		return [T_FUNCTION];
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
	 * @param \PHP_CodeSniffer\Files\File $phpcsFile
	 * @param int $pointer
	 */
	public function process(File $phpcsFile, $pointer): void
	{
		if (!FunctionHelper::isMethod($phpcsFile, $pointer)) {
			return;
		}

		$tokens = $phpcsFile->getTokens();

		$lineStartPointer = $phpcsFile->findFirstOnLine(T_OPEN_TAG, $pointer, true);
		assert(!is_bool($lineStartPointer));

		$methodDeclarationEndPointer = $phpcsFile->findNext([T_OPEN_CURLY_BRACKET, T_SEMICOLON], $pointer);
		assert(is_int($methodDeclarationEndPointer));

		$declarationEndLine = $tokens[$methodDeclarationEndPointer]['line'];
		if (in_array($tokens[$lineStartPointer]['line'], [$declarationEndLine, $declarationEndLine - 1], true)) {
			return;
		}

		$singleLineMethodDeclarationEndPointer = $methodDeclarationEndPointer;
		if ($tokens[$methodDeclarationEndPointer]['code'] === T_OPEN_CURLY_BRACKET) {
			$singleLineMethodDeclarationEndPointer--;
		}

		$methodDeclaration = TokenHelper::getContent($phpcsFile, $lineStartPointer, $singleLineMethodDeclarationEndPointer);
		$methodDeclaration = preg_replace(sprintf('~%s[ \t]*~', $phpcsFile->eolChar), ' ', $methodDeclaration);
		assert(is_string($methodDeclaration));

		$methodDeclaration = str_replace(['( ', ' )'], ['(', ')'], $methodDeclaration);
		$methodDeclaration = rtrim($methodDeclaration);

		$methodDeclarationWithoutTabIndetation = preg_replace_callback('~^(\t+)~', static function (array $matches): string {
			return str_repeat('    ', strlen($matches[1]));
		}, $methodDeclaration);

		if ($this->maxLineLength !== 0 && strlen($methodDeclarationWithoutTabIndetation) > $this->maxLineLength) {
			return;
		}

		$error = sprintf('Method "%s" can be placed on a single line.', FunctionHelper::getName($phpcsFile, $pointer));
		$fix = $phpcsFile->addFixableError($error, $pointer, self::CODE_UNNECESSARY_MULTI_LINE_METHOD);
		if (!$fix) {
			return;
		}

		$whitespaceBeforeMethod = $tokens[$lineStartPointer]['content'];

		$phpcsFile->fixer->beginChangeset();

		for ($i = $lineStartPointer; $i <= $methodDeclarationEndPointer; $i++) {
			$phpcsFile->fixer->replaceToken($i, '');
		}

		$replacement = $methodDeclaration;
		if ($tokens[$methodDeclarationEndPointer]['code'] === T_OPEN_CURLY_BRACKET) {
			$replacement = sprintf("%s\n%s{", $methodDeclaration, $whitespaceBeforeMethod);
		}

		$phpcsFile->fixer->replaceToken($lineStartPointer, $replacement);

		$phpcsFile->fixer->endChangeset();
	}

}