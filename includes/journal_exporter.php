<?php
/**
 * Journal Exporter Class
 * 
 * Exports automated test results and AI validation journals
 * to PDF and XML formats for documentation and audit trails.
 * 
 * @author CurenexAI
 * @version 1.0.0
 */

require_once __DIR__ . '/../vendor/autoload.php';

class JournalExporter {
    private $results;
    private $summary;
    private $journal;
    private $exportPath;
    
    /**
     * Constructor
     * @param array $results Test results from ConsultationAutomator
     * @param array $summary Summary statistics
     * @param array $journal Journal entries
     */
    public function __construct($results, $summary, $journal) {
        $this->results = $results;
        $this->summary = $summary;
        $this->journal = $journal;
        $this->exportPath = __DIR__ . '/../logs/journals/';
        
        // Create export directory if not exists
        if (!is_dir($this->exportPath)) {
            mkdir($this->exportPath, 0755, true);
        }
    }
    
    /**
     * Export to PDF format using TCPDF
     * @param string|null $filename Custom filename (without extension)
     * @return string Path to generated PDF
     */
    public function exportToPDF($filename = null) {
        $filename = $filename ?? 'ai_test_journal_' . date('Y-m-d_His');
        $filepath = $this->exportPath . $filename . '.pdf';
        
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('CurenexAI Automator');
        $pdf->SetAuthor('CurenexAI');
        $pdf->SetTitle('AI Consultation Test Journal');
        $pdf->SetSubject('RAG & Gemini AI Testing Results');
        $pdf->SetKeywords('Homeopathy, AI, RAG, Gemini, Testing, Consultation');
        
        // Set default header data
        $pdf->SetHeaderData('', 0, 'CurenexAI - AI Test Journal', 'Generated: ' . date('Y-m-d H:i:s'));
        
        // Set header and footer fonts
        $pdf->setHeaderFont(['helvetica', '', 10]);
        $pdf->setFooterFont(['helvetica', '', 8]);
        
        // Set default monospaced font
        $pdf->SetDefaultMonospacedFont('courier');
        
        // Set margins
        $pdf->SetMargins(15, 27, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        
        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 25);
        
        // Set font
        $pdf->SetFont('helvetica', '', 10);
        
        // Add first page
        $pdf->AddPage();
        
        // Generate HTML content
        $html = $this->generatePDFContent();
        
        // Write HTML
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Save PDF
        $pdf->Output($filepath, 'F');
        
        return $filepath;
    }
    
    /**
     * Generate HTML content for PDF
     */
    private function generatePDFContent() {
        $html = '';
        
        // Title
        $html .= '<h1 style="color:#2C3E50; text-align:center;">AI Consultation Test Journal</h1>';
        $html .= '<p style="text-align:center; color:#7F8C8D;">Automated RAG & Gemini AI Testing Report</p>';
        $html .= '<hr style="border-color:#3498DB;"/>';
        
        // Summary Section
        $html .= $this->generateSummarySectionHTML();
        
        // Detailed Results Section
        $html .= $this->generateResultsSectionHTML();
        
        // Journal Log Section
        $html .= $this->generateJournalSectionHTML();
        
        return $html;
    }
    
    /**
     * Generate summary section HTML
     */
    private function generateSummarySectionHTML() {
        $html = '<h2 style="color:#2980B9;">📊 Test Summary</h2>';
        
        // Status color based on pass rate
        $passRate = $this->summary['pass_rate'] ?? 0;
        $statusColor = $passRate >= 80 ? '#27AE60' : ($passRate >= 50 ? '#F39C12' : '#E74C3C');
        
        $html .= '<table cellpadding="5" cellspacing="0" border="1" style="border-color:#BDC3C7; width:100%;">';
        $html .= '<tr style="background-color:#3498DB; color:white;">
                    <th colspan="4" style="text-align:center;">Test Execution Summary</th>
                  </tr>';
        
        $html .= '<tr>
                    <td style="width:25%;"><strong>Total Tests:</strong></td>
                    <td style="width:25%;">' . ($this->summary['total_tests'] ?? 0) . '</td>
                    <td style="width:25%;"><strong>Pass Rate:</strong></td>
                    <td style="width:25%; color:' . $statusColor . '; font-weight:bold;">' . $passRate . '%</td>
                  </tr>';
        
        $html .= '<tr style="background-color:#F8F9FA;">
                    <td><strong>Passed:</strong></td>
                    <td style="color:#27AE60;">' . ($this->summary['passed'] ?? 0) . '</td>
                    <td><strong>Failed:</strong></td>
                    <td style="color:#E74C3C;">' . ($this->summary['failed'] ?? 0) . '</td>
                  </tr>';
        
        $html .= '<tr>
                    <td><strong>Warnings:</strong></td>
                    <td style="color:#F39C12;">' . ($this->summary['warnings'] ?? 0) . '</td>
                    <td><strong>Errors:</strong></td>
                    <td style="color:#9B59B6;">' . ($this->summary['errors'] ?? 0) . '</td>
                  </tr>';
        
        $html .= '<tr style="background-color:#F8F9FA;">
                    <td><strong>Avg RAG Time:</strong></td>
                    <td>' . ($this->summary['avg_rag_response_time'] ?? 0) . 's</td>
                    <td><strong>Avg Gemini Time:</strong></td>
                    <td>' . ($this->summary['avg_gemini_response_time'] ?? 0) . 's</td>
                  </tr>';
        
        $html .= '<tr>
                    <td><strong>Total RAG Remedies:</strong></td>
                    <td>' . ($this->summary['total_rag_remedies'] ?? 0) . '</td>
                    <td><strong>Total Gemini Remedies:</strong></td>
                    <td>' . ($this->summary['total_gemini_remedies'] ?? 0) . '</td>
                  </tr>';
        
        $html .= '<tr style="background-color:#F8F9FA;">
                    <td colspan="2"><strong>Total Execution Time:</strong></td>
                    <td colspan="2">' . ($this->summary['total_execution_time'] ?? 0) . ' seconds</td>
                  </tr>';
        
        $html .= '</table><br/>';
        
        return $html;
    }
    
    /**
     * Generate detailed results section HTML
     */
    private function generateResultsSectionHTML() {
        $html = '<h2 style="color:#2980B9; page-break-before:always;">📋 Detailed Test Results</h2>';
        
        foreach ($this->results as $index => $result) {
            $statusColor = match($result['overall_status']) {
                'PASS' => '#27AE60',
                'WARN' => '#F39C12',
                'FAIL' => '#E74C3C',
                default => '#9B59B6'
            };
            
            $statusIcon = match($result['overall_status']) {
                'PASS' => '✓',
                'WARN' => '⚠',
                'FAIL' => '✗',
                default => '?'
            };
            
            $html .= '<div style="border:1px solid #BDC3C7; padding:10px; margin-bottom:15px; border-radius:5px;">';
            
            // Test header
            $html .= '<table cellpadding="3" style="width:100%; background-color:#ECF0F1;">
                        <tr>
                            <td style="width:60%;"><strong>Test #' . $result['test_number'] . ': ' . htmlspecialchars($result['name']) . '</strong></td>
                            <td style="width:20%;"><strong>Status:</strong> <span style="color:' . $statusColor . ';">' . $statusIcon . ' ' . $result['overall_status'] . '</span></td>
                            <td style="width:20%;"><strong>Time:</strong> ' . $result['execution_time'] . 's</td>
                        </tr>
                      </table>';
            
            // RAG Results
            $html .= '<h4 style="color:#16A085;">🔍 RAG Results</h4>';
            $html .= '<table cellpadding="3" style="width:100%; font-size:9px;">
                        <tr>
                            <td><strong>Method:</strong> ' . ($result['rag_result']['method'] ?? 'N/A') . '</td>
                            <td><strong>Response Time:</strong> ' . ($result['rag_result']['response_time'] ?? 0) . 's</td>
                            <td><strong>Remedies Found:</strong> ' . count($result['rag_result']['remedies'] ?? []) . '</td>
                        </tr>
                      </table>';
            
            if (!empty($result['rag_result']['remedies'])) {
                $html .= '<p style="font-size:9px; margin-left:10px;"><em>Remedies: ';
                $remedyNames = array_slice(array_column($result['rag_result']['remedies'], 'name'), 0, 5);
                $html .= htmlspecialchars(implode(', ', $remedyNames));
                if (count($result['rag_result']['remedies']) > 5) {
                    $html .= ' (+' . (count($result['rag_result']['remedies']) - 5) . ' more)';
                }
                $html .= '</em></p>';
            }
            
            if (!empty($result['rag_result']['error'])) {
                $html .= '<p style="color:#E74C3C; font-size:9px;">Error: ' . htmlspecialchars($result['rag_result']['error']) . '</p>';
            }
            
            // Gemini Results
            $html .= '<h4 style="color:#8E44AD;">🤖 Gemini AI Results</h4>';
            $html .= '<table cellpadding="3" style="width:100%; font-size:9px;">
                        <tr>
                            <td><strong>Model:</strong> ' . ($result['gemini_result']['model'] ?? 'N/A') . '</td>
                            <td><strong>Response Time:</strong> ' . ($result['gemini_result']['response_time'] ?? 0) . 's</td>
                            <td><strong>Remedies Found:</strong> ' . count($result['gemini_result']['remedies'] ?? []) . '</td>
                        </tr>
                      </table>';
            
            if (!empty($result['gemini_result']['remedies'])) {
                $html .= '<p style="font-size:9px; margin-left:10px;"><em>Remedies: ';
                $remedyNames = array_slice(array_column($result['gemini_result']['remedies'], 'name'), 0, 5);
                $html .= htmlspecialchars(implode(', ', $remedyNames));
                if (count($result['gemini_result']['remedies']) > 5) {
                    $html .= ' (+' . (count($result['gemini_result']['remedies']) - 5) . ' more)';
                }
                $html .= '</em></p>';
            }
            
            if (!empty($result['gemini_result']['error'])) {
                $html .= '<p style="color:#E74C3C; font-size:9px;">Error: ' . htmlspecialchars($result['gemini_result']['error']) . '</p>';
            }
            
            // Remarks
            if (!empty($result['remarks'])) {
                $html .= '<h4 style="color:#2C3E50;">📝 Remarks</h4>';
                $html .= '<ul style="font-size:9px;">';
                foreach ($result['remarks'] as $remark) {
                    $remarkColor = match($remark['type']) {
                        'success' => '#27AE60',
                        'info' => '#3498DB',
                        'warning' => '#F39C12',
                        'error' => '#E74C3C',
                        default => '#7F8C8D'
                    };
                    $html .= '<li style="color:' . $remarkColor . ';">[' . strtoupper($remark['source']) . '] ' . htmlspecialchars($remark['message']) . '</li>';
                }
                $html .= '</ul>';
            }
            
            // Validation Results
            if (!empty($result['validation'])) {
                $html .= '<h4 style="color:#34495E;">✅ Validation Checks</h4>';
                $html .= '<table cellpadding="2" cellspacing="0" border="1" style="border-color:#BDC3C7; width:100%; font-size:8px;">
                            <tr style="background-color:#ECF0F1;">
                                <th>Check</th>
                                <th>Status</th>
                                <th>Expected</th>
                                <th>Actual</th>
                            </tr>';
                foreach ($result['validation'] as $checkName => $check) {
                    $checkColor = match($check['status']) {
                        'PASS' => '#27AE60',
                        'WARN' => '#F39C12',
                        'FAIL' => '#E74C3C',
                        default => '#7F8C8D'
                    };
                    $html .= '<tr>
                                <td>' . htmlspecialchars(str_replace('_', ' ', ucfirst($checkName))) . '</td>
                                <td style="color:' . $checkColor . ';">' . $check['status'] . '</td>
                                <td>' . htmlspecialchars($check['expected']) . '</td>
                                <td>' . htmlspecialchars($check['actual']) . '</td>
                              </tr>';
                }
                $html .= '</table>';
            }
            
            $html .= '</div>';
        }
        
        return $html;
    }
    
    /**
     * Generate journal log section HTML
     */
    private function generateJournalSectionHTML() {
        $html = '<h2 style="color:#2980B9; page-break-before:always;">📜 Execution Journal</h2>';
        $html .= '<p style="font-size:9px; color:#7F8C8D;">Detailed log of automated test execution events</p>';
        
        $html .= '<table cellpadding="3" cellspacing="0" border="1" style="border-color:#BDC3C7; width:100%; font-size:8px;">
                    <tr style="background-color:#3498DB; color:white;">
                        <th style="width:15%;">Timestamp</th>
                        <th style="width:8%;">Level</th>
                        <th style="width:25%;">Message</th>
                        <th style="width:52%;">Data</th>
                    </tr>';
        
        foreach ($this->journal as $entry) {
            $levelColor = match($entry['level']) {
                'DEBUG' => '#7F8C8D',
                'INFO' => '#3498DB',
                'WARNING' => '#F39C12',
                'ERROR' => '#E74C3C',
                default => '#2C3E50'
            };
            
            $dataStr = '';
            if (!empty($entry['data'])) {
                $dataStr = htmlspecialchars(json_encode($entry['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            
            $html .= '<tr>
                        <td>' . htmlspecialchars($entry['timestamp']) . '</td>
                        <td style="color:' . $levelColor . ';">' . htmlspecialchars($entry['level']) . '</td>
                        <td>' . htmlspecialchars($entry['message']) . '</td>
                        <td><pre style="margin:0; font-size:7px;">' . $dataStr . '</pre></td>
                      </tr>';
        }
        
        $html .= '</table>';
        
        return $html;
    }
    
    /**
     * Export to XML format
     * @param string|null $filename Custom filename (without extension)
     * @return string Path to generated XML
     */
    public function exportToXML($filename = null) {
        $filename = $filename ?? 'ai_test_journal_' . date('Y-m-d_His');
        $filepath = $this->exportPath . $filename . '.xml';
        
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('    ');
        
        $xml->startDocument('1.0', 'UTF-8');
        
        // Root element
        $xml->startElement('AITestJournal');
        $xml->writeAttribute('generatedAt', date('c'));
        $xml->writeAttribute('application', 'CurenexAI');
        $xml->writeAttribute('version', '1.0.0');
        
        // Summary section
        $xml->startElement('Summary');
        foreach ($this->summary as $key => $value) {
            $xml->writeElement($this->camelToSnake($key), (string)$value);
        }
        $xml->endElement(); // Summary
        
        // Results section
        $xml->startElement('TestResults');
        foreach ($this->results as $result) {
            $xml->startElement('TestCase');
            $xml->writeAttribute('number', $result['test_number']);
            $xml->writeAttribute('status', $result['overall_status']);
            $xml->writeAttribute('executionTime', $result['execution_time']);
            
            $xml->writeElement('Name', $result['name']);
            $xml->writeElement('PatientId', (string)($result['patient_id'] ?? ''));
            $xml->writeElement('ConsultationId', (string)($result['consultation_id'] ?? ''));
            
            // RAG Results
            $xml->startElement('RAGResult');
            $xml->writeAttribute('success', $result['rag_result']['success'] ? 'true' : 'false');
            $xml->writeElement('Method', $result['rag_result']['method'] ?? '');
            $xml->writeElement('ResponseTime', (string)($result['rag_result']['response_time'] ?? 0));
            $xml->writeElement('Error', $result['rag_result']['error'] ?? '');
            
            $xml->startElement('Remedies');
            foreach (($result['rag_result']['remedies'] ?? []) as $remedy) {
                $xml->startElement('Remedy');
                $xml->writeElement('Name', $remedy['name'] ?? '');
                $xml->writeElement('Score', (string)($remedy['score'] ?? 0));
                $xml->writeElement('Indication', $remedy['indication'] ?? '');
                $xml->endElement(); // Remedy
            }
            $xml->endElement(); // Remedies
            $xml->endElement(); // RAGResult
            
            // Gemini Results
            $xml->startElement('GeminiResult');
            $xml->writeAttribute('success', $result['gemini_result']['success'] ? 'true' : 'false');
            $xml->writeElement('Model', $result['gemini_result']['model'] ?? '');
            $xml->writeElement('ResponseTime', (string)($result['gemini_result']['response_time'] ?? 0));
            $xml->writeElement('Error', $result['gemini_result']['error'] ?? '');
            $xml->writeElement('CaseAnalysis', $result['gemini_result']['case_analysis'] ?? '');
            
            $xml->startElement('Remedies');
            foreach (($result['gemini_result']['remedies'] ?? []) as $remedy) {
                $xml->startElement('Remedy');
                $xml->writeElement('Name', $remedy['name'] ?? '');
                $xml->writeElement('Potency', $remedy['potency'] ?? '');
                $xml->writeElement('Indication', $remedy['indication'] ?? '');
                $xml->endElement(); // Remedy
            }
            $xml->endElement(); // Remedies
            $xml->endElement(); // GeminiResult
            
            // Validation
            $xml->startElement('Validation');
            foreach (($result['validation'] ?? []) as $checkName => $check) {
                $xml->startElement('Check');
                $xml->writeAttribute('name', $checkName);
                $xml->writeAttribute('status', $check['status']);
                $xml->writeElement('Expected', (string)$check['expected']);
                $xml->writeElement('Actual', (string)$check['actual']);
                $xml->endElement(); // Check
            }
            $xml->endElement(); // Validation
            
            // Remarks
            $xml->startElement('Remarks');
            foreach (($result['remarks'] ?? []) as $remark) {
                $xml->startElement('Remark');
                $xml->writeAttribute('type', $remark['type']);
                $xml->writeAttribute('source', $remark['source']);
                $xml->text($remark['message']);
                $xml->endElement(); // Remark
            }
            $xml->endElement(); // Remarks
            
            $xml->endElement(); // TestCase
        }
        $xml->endElement(); // TestResults
        
        // Journal section
        $xml->startElement('Journal');
        foreach ($this->journal as $entry) {
            $xml->startElement('Entry');
            $xml->writeAttribute('timestamp', $entry['timestamp']);
            $xml->writeAttribute('level', $entry['level']);
            $xml->writeElement('Message', $entry['message']);
            if (!empty($entry['data'])) {
                $xml->writeElement('Data', json_encode($entry['data']));
            }
            $xml->endElement(); // Entry
        }
        $xml->endElement(); // Journal
        
        $xml->endElement(); // AITestJournal
        $xml->endDocument();
        
        // Write to file
        file_put_contents($filepath, $xml->outputMemory());
        
        return $filepath;
    }
    
    /**
     * Export to both PDF and XML
     * @param string|null $filename Base filename (without extension)
     * @return array Paths to generated files
     */
    public function exportAll($filename = null) {
        $filename = $filename ?? 'ai_test_journal_' . date('Y-m-d_His');
        
        return [
            'pdf' => $this->exportToPDF($filename),
            'xml' => $this->exportToXML($filename)
        ];
    }
    
    /**
     * Convert camelCase to snake_case for XML element names
     */
    private function camelToSnake($input) {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $input));
    }
    
    /**
     * Get available export formats
     */
    public static function getAvailableFormats() {
        return ['pdf', 'xml'];
    }
}
