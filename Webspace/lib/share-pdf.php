<?php
  if ($shareFormat !== "entry_pdf") {
    return;
  }

  $pageWidth = 595;
  $pageHeight = 842;
  $margin = 40;
  $lineGap = 4;
  $titleSize = 16;
  $subtitleSize = 13;
  $textSize = 11;
  $rowHeight = 18;
  $headerHeight = 20;
  $tableWidth = $pageWidth - ($margin * 2);
  $col1Width = (int)round($tableWidth * 0.6);
  $col2Width = $tableWidth - $col1Width;

  $toPdfText = static function (string $text): string {
    $converted = @iconv("UTF-8", "Windows-1252//TRANSLIT", $text);
    if ($converted === false) {
      $converted = $text;
    }
    $converted = str_replace(["\\", "(", ")"], ["\\\\", "\\(", "\\)"], $converted);
    return preg_replace("/[\\r\\n]+/", " ", $converted);
  };

  $wrapText = static function (string $text, int $maxChars): array {
    $text = trim($text);
    if ($text === "" || $maxChars <= 0) {
      return $text === "" ? [] : [$text];
    }
    $wrapped = wordwrap($text, $maxChars, "\n", true);
    return array_values(array_filter(explode("\n", $wrapped), "strlen"));
  };

  $estimateChars = static function (int $fontSize, int $maxWidth): int {
    $avgCharWidth = max(1, (int)round($fontSize * 0.55));
    return max(1, (int)floor($maxWidth / $avgCharWidth));
  };

  $makeText = static function (int $x, int $y, int $size, string $text, string $font = "F1") use ($toPdfText): string {
    return "BT /{$font} {$size} Tf {$x} {$y} Td (" . $toPdfText($text) . ") Tj ET\n";
  };

  $makeLine = static function (int $x1, int $y1, int $x2, int $y2): string {
    return "{$x1} {$y1} m {$x2} {$y2} l S\n";
  };

  $makeRect = static function (int $x, int $y, int $w, int $h): string {
    return "{$x} {$y} {$w} {$h} re S\n";
  };

  $buildPage = static function (array $rows, ?array $bgImage) use (
    $pageHeight,
    $margin,
    $lineGap,
    $titleSize,
    $subtitleSize,
    $textSize,
    $rowHeight,
    $headerHeight,
    $tableWidth,
    $col1Width,
    $col2Width,
    $makeText,
    $makeLine,
    $makeRect,
    $wrapText,
    $estimateChars,
    $pdfTitle,
    $pdfDiscipline,
    $pdfDescription,
    $pdfResultHeader
  ): string {
    $content = "q 0 0 0 RG 0 0 0 rg 1 w\n";
    $y = $pageHeight - $margin;

    if ($bgImage) {
      $imgWidth = (int)$bgImage["width"];
      $imgHeight = (int)$bgImage["height"];
      $targetWidth = (int)round($bgImage["targetWidth"]);
      $targetHeight = (int)round($targetWidth * ($imgHeight / max(1, $imgWidth)));
      $xPos = (int)round(($bgImage["pageWidth"] - $targetWidth) / 2);
      $yPos = (int)round(($bgImage["pageHeight"] - $targetHeight) / 2);
      $content .= "q /GS1 gs {$targetWidth} 0 0 {$targetHeight} {$xPos} {$yPos} cm /Im1 Do Q\n";
    }

    if ($pdfTitle !== "") {
      $content .= $makeText($margin, $y, $titleSize, $pdfTitle);
      $y -= ($titleSize + $lineGap);
    }
    if ($pdfDiscipline !== "") {
      $content .= $makeText($margin, $y, $subtitleSize, $pdfDiscipline);
      $y -= ($subtitleSize + $lineGap);
    }
    if ($pdfDescription !== "") {
      $maxChars = $estimateChars($textSize, $tableWidth);
      foreach ($wrapText($pdfDescription, $maxChars) as $line) {
        $content .= $makeText($margin, $y, $textSize, $line);
        $y -= ($textSize + $lineGap);
      }
    }
    $y -= 8;

    $tableTop = $y;
    $content .= $makeRect($margin, $tableTop - $headerHeight, $tableWidth, $headerHeight);
    $content .= $makeLine($margin + $col1Width, $tableTop, $margin + $col1Width, $tableTop - $headerHeight);
    $content .= $makeText($margin + 6, $tableTop - $headerHeight + 5, $textSize, t("combine.entry.pdf_athlete", "Athlet"), "F2");
    $content .= $makeText($margin + $col1Width + 6, $tableTop - $headerHeight + 5, $textSize, $pdfResultHeader, "F2");

    $y = $tableTop - $headerHeight;
    foreach ($rows as $row) {
      $y -= $rowHeight;
      $content .= $makeRect($margin, $y, $tableWidth, $rowHeight);
      $content .= $makeLine($margin + $col1Width, $y, $margin + $col1Width, $y + $rowHeight);
      $content .= $makeText($margin + 6, $y + 5, $textSize, $row[0] ?? "");
    }

    $content .= "Q\n";
    return $content;
  };

  $pages = [];
  $bgImage = null;
  $logoPath = __DIR__ . "/../assets/FrisbeeCatch.png";
  if (function_exists("imagecreatefrompng") && file_exists($logoPath)) {
    $image = @imagecreatefrompng($logoPath);
    if ($image) {
      $imgWidth = imagesx($image);
      $imgHeight = imagesy($image);
      ob_start();
      imagejpeg($image, null, 80);
      $jpegData = ob_get_clean();
      imagedestroy($image);
      if (is_string($jpegData) && $jpegData !== "") {
        $bgImage = [
          "data" => $jpegData,
          "width" => $imgWidth,
          "height" => $imgHeight,
          "targetWidth" => (int)round($pageWidth * 0.7),
          "pageWidth" => $pageWidth,
          "pageHeight" => $pageHeight,
        ];
      }
    }
  }
  $rowIndex = 0;
  $totalRows = count($pdfRows);
  while ($rowIndex < $totalRows) {
    $availableHeight = $pageHeight - ($margin * 2);
    $headerBlock = ($titleSize + $lineGap) + ($subtitleSize + $lineGap);
    if (trim((string)$pdfDescription) !== "") {
      $maxChars = $estimateChars($textSize, $tableWidth);
      $lines = $wrapText((string)$pdfDescription, $maxChars);
      $headerBlock += count($lines) * ($textSize + $lineGap);
    }
    $headerBlock += 8 + $headerHeight;
    $usable = $availableHeight - $headerBlock;
    $rowsPerPage = max(1, (int)floor($usable / $rowHeight));
    $pageRows = array_slice($pdfRows, $rowIndex, $rowsPerPage);
    $pages[] = $buildPage($pageRows, $bgImage);
    $rowIndex += $rowsPerPage;
  }

  $objects = [];
  $addObj = static function (string $content) use (&$objects): int {
    $objects[] = $content;
    return count($objects);
  };

  $pageIds = [];
  $contentIds = [];
  foreach ($pages as $pageContent) {
    $contentIds[] = $addObj("<< /Length " . strlen($pageContent) . " >>\nstream\n" . $pageContent . "endstream");
  }

  $fontId = $addObj("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>");
  $fontBoldId = $addObj("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>");
  $gsId = null;
  $imageId = null;
  if ($bgImage) {
    $gsId = $addObj("<< /Type /ExtGState /ca 0.08 /CA 0.08 >>");
    $imageId = $addObj("<< /Type /XObject /Subtype /Image /Width {$bgImage["width"]} /Height {$bgImage["height"]} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($bgImage["data"]) . " >>\nstream\n" . $bgImage["data"] . "\nendstream");
  }
  $pagesId = $addObj("<< /Type /Pages /Kids [] /Count " . count($pages) . " >>");
  $catalogId = $addObj("<< /Type /Catalog /Pages {$pagesId} 0 R >>");

  for ($i = 0; $i < count($pages); $i += 1) {
    $contentId = $contentIds[$i];
    $resources = "<< /Font << /F1 {$fontId} 0 R /F2 {$fontBoldId} 0 R >>";
    if ($gsId && $imageId) {
      $resources .= " /ExtGState << /GS1 {$gsId} 0 R >> /XObject << /Im1 {$imageId} 0 R >>";
    }
    $resources .= " >>";
    $pageObj = "<< /Type /Page /Parent {$pagesId} 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources {$resources} /Contents {$contentId} 0 R >>";
    $pageIds[] = $addObj($pageObj);
  }

  $kids = implode(" ", array_map(static function ($id) {
    return $id . " 0 R";
  }, $pageIds));
  $objects[$pagesId - 1] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($pageIds) . " >>";

  $pdf = "%PDF-1.4\n";
  $offsets = [0];
  foreach ($objects as $index => $obj) {
    $offsets[] = strlen($pdf);
    $objNum = $index + 1;
    $pdf .= $objNum . " 0 obj\n" . $obj . "\nendobj\n";
  }
  $xrefOffset = strlen($pdf);
  $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
  $pdf .= "0000000000 65535 f \n";
  for ($i = 1; $i <= count($objects); $i += 1) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
  }
  $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root {$catalogId} 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

  header("Content-Type: application/pdf");
  header("Content-Disposition: attachment; filename=\"" . $shareFileBase . ".pdf\"");
  echo $pdf;
  exit;
