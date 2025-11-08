<?php

class TableRow
{
	#region Private Static Fields
	private static $cleanTypeRegex = '#\bdata-cleantype\s*=\s*([\'"]?)(?<cleantype>\w+)\1#';
	#endregion

	#region Fields
	/** @var TableCell[] $cells The cells in the row. */
	private $cells = [];

	/** @var string $openTag The full <tr> tag that started this row. */
	private $openTag;
	#endregion

	#region Public Properties
	/** @var string $cleanType The cleaning strategy to use for this row:
	 *     auto  : Use the automatic settings. Only useful to override a previous value.
	 *     clean : Always clean this row; mostly useful for debugging and format testing.
	 *     header: Treat this row like a header and show or hide it accordingly.
	 *     keep  : Always keep this row.
	 *     normal: Treat this row like a normal row and show or hide it accordingly.
	 *     tableheader: Remove this row if the entire table is being removed; otherwise always keep it.
	 */
	public $cleanType;
	public $hasContent = false;
	public $isHeader = true;
	public $cellCount = 0;
	#endregion

	#region Constructor
	/**
	 * Creates an instance of a TableRow.
	 *
	 * @param string $openTag The full <tr> tag.
	 */
	public function __construct(string $openTag)
	{
		$this->openTag = $openTag;
		preg_match(self::$cleanTypeRegex, $openTag, $matches);
		$this->cleanType = empty($matches) ? 'auto' : $matches['cleantype'];
	}
	#endregion

	#region Public Functions
	/**
	 * Adds raw cells to this row from regex matches.
	 *
	 * @param TableRow[] $map The table row map.
	 * @param int $rowNum The current row number.
	 * @param string[][] $rawCells The raw cell matches.
	 */
	public function addRawCells(array &$map, int $rowNum, array $rawCells): void
	{
		$cellNum = 0;
		$rowCount = count($map);
		foreach ($rawCells as $rawCell) {
			while (isset($this->cells[$cellNum])) {
				$cellNum++;
			}

			$cell = TableCell::FromMatch($rawCell);
			$this->setCell($cellNum, $cell);
			$rowspan = $cell->rowspan;
			$colspan = $cell->colspan;
			#RHshow("Cell ($rowNum, $cellNum)", $cell);
			if ($rowspan > 1 || $colspan > 1) {
				// If cell is a span, add children that point back to the parent.
				$spanCell = TableCell::SpanChild($cell);
				for ($r = 0; $r < $rowspan; $r++) {
					for ($c = 0; $c < $colspan; $c++) {
						if ($r || $c) {
							$rowOffset = $rowNum + $r;
							if ($rowOffset < $rowCount) {
								$cellOffset = $cellNum + $c;
								#RHshow("Span Child ($rowOffset, $cellOffset)", $spanCell->parent);
								$map[$rowOffset]->setCell($cellOffset, $spanCell);
							}
						}
					}
				}
			}

			$this->isHeader &= $cell->isHeader;
			$this->hasContent |= !$cell->isHeader && (bool)strlen(trim($cell->content));
			$cellNum++;
		}
	}

	/**
	 * Reduces the rowspan of all span cells in this row by one.
	 */
	public function decrementRowspan(): void
	{
		$parents = [];
		foreach ($this->cells as $cell) {
			$parent = $cell->parent;
			if ($parent && !in_array($parent, $parents, true)) {
				$parents[] = $parent;
				$parent->decrementRowspan();
			}
		}
	}

	/**
	 * Gets the number of columns in this row.
	 *
	 * @return int The number of columns.
	 */
	public function getColumnCount(): int
	{
		return count($this->cells);
	}

	/**
	 * Serializes the TableRow to HTML.
	 *
	 * @return string
	 */
	public function toHtml(): string
	{
		$output = "$this->openTag\n";
		foreach ($this->cells as $cell) {
			$html = $cell->toHtml();
			if ($html) {
				$output .= "$html\n";
			}
		}

		return $output . "</tr>\n";
	}

	/**
	 * Updates whether this row has content based on its cells.
	 *
	 * @param bool $cleanImages Whether to remove images when checking for content.
	 */
	public function updateHasContent(bool $cleanImages): void
	{
		$hasContent = false;
		foreach ($this->cells as $cell) {
			if (is_null($cell->parent) && !$cell->isHeader) {
				// Don't clean images that take up more than one cell in a non-header row; treat as always wanted.
				$protectedImage = $cell->colspan > 1;
				$trimmedContent = $cell->getTrimmedContent($cleanImages && !$protectedImage);
				$hasContent |= (bool)strlen($trimmedContent);
			}
		}

		$this->hasContent = $hasContent;
	}
	#endregion

	#region Private Functions
	/**
	 * Sets a cell at the given column index.
	 *
	 * @param int $col The column index.
	 * @param TableCell $cell The cell to set.
	 *
	 * @comments This function counts span cells as a single cell.
	 */
	private function setCell(int $col, TableCell $cell): void
	{
		// Count cells, treating spans as a single cell.
		if (!$cell->parent) {
			$this->cellCount++;
		}

		$this->cells[$col] = $cell;
	}
	#endregion
}
