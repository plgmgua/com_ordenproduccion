<?php
/**
 * @package     Grimpsa.Component.Ordenproduccion
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Tests\Unit\Helper;

use Grimpsa\Component\Ordenproduccion\Site\Helper\CotizacionFpdfBlocksHelper;
use PHPUnit\Framework\TestCase;

/**
 * @since  3.119.329
 */
class CotizacionFpdfBlocksHelperTest extends TestCase
{
    public function testCssFontSizeToPointsConvertsPxAndPt(): void
    {
        $this->assertSame(7.5, CotizacionFpdfBlocksHelper::cssFontSizeToPoints('10px'));
        $this->assertSame(12.0, CotizacionFpdfBlocksHelper::cssFontSizeToPoints('12pt'));
    }

    public function testParseHtmlBlocksCapturesListFontSizeFromUlStyle(): void
    {
        $html = '<ul style="font-size: 10px"><li>Precios incluyen IVA.</li></ul>';
        $blocks = CotizacionFpdfBlocksHelper::parseHtmlBlocks($html, static fn ($t) => $t);

        $this->assertCount(1, $blocks);
        $this->assertTrue($blocks[0]['list']);
        $this->assertSame(7.5, $blocks[0]['fontSize']);
        $this->assertStringContainsString('Precios incluyen IVA.', $blocks[0]['text']);
    }

    public function testParseHtmlBlocksCapturesParagraphFontSize(): void
    {
        $html = '<p style="font-size: 12pt">Nota importante.</p>';
        $blocks = CotizacionFpdfBlocksHelper::parseHtmlBlocks($html, static fn ($t) => $t);

        $this->assertCount(1, $blocks);
        $this->assertSame(12.0, $blocks[0]['fontSize']);
        $this->assertSame('Nota importante.', $blocks[0]['text']);
    }
}
