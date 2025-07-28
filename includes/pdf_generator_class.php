<?php
/**
 * Class GRG_PDF_Generator
 * Handles PDF generation using TCPDF or fallback methods
 */

if (!defined('ABSPATH')) {
    exit;
}

class GRG_PDF_Generator {
    
    /**
     * Generate PDF using TCPDF
     */
    public static function generate_tcpdf_report($genetic_data, $report_type, $product_name, $upload_id, $order_id) {
        if (!class_exists('TCPDF')) {
            return null;
        }
        
        try {
            // Create new PDF document
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('MTHFRSupport Genetic Report Generator');
            $pdf->SetAuthor('MTHFRSupport.org');
            $pdf->SetTitle("$report_type Genetic Report");
            $pdf->SetSubject('Genetic Analysis Report');
            
            // Set default header data
            $pdf->SetHeaderData('', 0, "MTHFRSupport $report_type Genetic Report", 
                "Generated: " . date('F j, Y g:i A') . "\nReport ID: $upload_id-$order_id");
            
            // Set header and footer fonts
            $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
            $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
            
            // Set default monospaced font
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            
            // Set margins
            $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
            $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
            $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
            
            // Set auto page breaks
            $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
            
            // Set image scale factor
            $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
            
            // Add a page
            $pdf->AddPage();
            
            // Set font
            $pdf->SetFont('helvetica', '', 12);
            
            // Title
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->Cell(0, 10, "MTHFRSupport $report_type Genetic Report", 0, 1, 'C');
            $pdf->Ln(5);
            
            // Report Info
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(40, 8, 'Product:', 0, 0, 'L');
            $pdf->Cell(0, 8, $product_name, 0, 1, 'L');
            $pdf->Cell(40, 8, 'Report ID:', 0, 0, 'L');
            $pdf->Cell(0, 8, "$upload_id-$order_id", 0, 1, 'L');
            $pdf->Cell(40, 8, 'Generated:', 0, 0, 'L');
            $pdf->Cell(0, 8, date('F j, Y g:i A'), 0, 1, 'L');
            $pdf->Cell(40, 8, 'Total Variants:', 0, 0, 'L');
            $pdf->Cell(0, 8, count($genetic_data), 0, 1, 'L');
            $pdf->Ln(10);
            
            // Summary section
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 10, 'Report Summary', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);
            
            // Count results
            $result_counts = array('+/+' => 0, '+/-' => 0, '-/-' => 0);
            foreach ($genetic_data as $variant) {
                $risk_count = substr_count($variant['genotype'], $variant['risk_allele']);
                if ($risk_count >= 2) {
                    $result_counts['+/+']++;
                } elseif ($risk_count === 1) {
                    $result_counts['+/-']++;
                } else {
                    $result_counts['-/-']++;
                }
            }
            
            $pdf->Cell(40, 8, 'High Risk (+/+):', 0, 0, 'L');
            $pdf->Cell(0, 8, $result_counts['+/+'] . ' variants', 0, 1, 'L');
            $pdf->Cell(40, 8, 'Medium Risk (+/-):', 0, 0, 'L');
            $pdf->Cell(0, 8, $result_counts['+/-'] . ' variants', 0, 1, 'L');
            $pdf->Cell(40, 8, 'Low Risk (-/-):', 0, 0, 'L');
            $pdf->Cell(0, 8, $result_counts['-/-'] . ' variants', 0, 1, 'L');
            $pdf->Ln(15);
            
            // Detailed Results
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 10, 'Detailed Genetic Analysis', 0, 1, 'L');
            $pdf->Ln(5);
            
            // Create table
            $pdf->SetFont('helvetica', 'B', 8);
            
            // Table header
            $header = array('SNP ID', 'Gene/Variant', 'Your Result', 'Risk Allele', 'Impact', 'Pathway');
            $w = array(20, 35, 20, 20, 20, 75); // Column widths
            
            // Header row
            $pdf->SetFillColor(51, 122, 183); // Blue background
            $pdf->SetTextColor(255, 255, 255); // White text
            for ($i = 0; $i < count($header); $i++) {
                $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', true);
            }
            $pdf->Ln();
            
            // Data rows
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(0, 0, 0); // Black text
            
            $target_snps = self::get_target_snps();
            $fill = false;
            
            foreach ($genetic_data as $variant) {
                $rsid = $variant['rsid'];
                $genotype = $variant['genotype'];
                $risk_allele = $variant['risk_allele'];
                
                // Calculate result and impact
                $risk_count = substr_count($genotype, $risk_allele);
                if ($risk_count >= 2) {
                    $result = "+/+";
                    $impact = "High";
                    $pdf->SetFillColor(255, 204, 204); // Light red
                } elseif ($risk_count === 1) {
                    $result = "+/-";
                    $impact = "Medium";
                    $pdf->SetFillColor(255, 255, 204); // Light yellow
                } else {
                    $result = "-/-";
                    $impact = "Low";
                    $pdf->SetFillColor(204, 255, 204); // Light green
                }
                
                // Get SNP info
                $snp_info = isset($target_snps[$rsid]) ? $target_snps[$rsid] : array(
                    'name' => $rsid,
                    'pathway' => 'Other'
                );
                
                // Truncate long names for table
                $gene_name = $snp_info['name'];
                if (strlen($gene_name) > 25) {
                    $gene_name = substr($gene_name, 0, 22) . "...";
                }
                
                $pathway = $snp_info['pathway'];
                if (strlen($pathway) > 30) {
                    $pathway = substr($pathway, 0, 27) . "...";
                }
                
                // Table row
                $pdf->Cell($w[0], 6, $rsid, 1, 0, 'C', $fill);
                $pdf->Cell($w[1], 6, $gene_name, 1, 0, 'L', $fill);
                $pdf->Cell($w[2], 6, $genotype, 1, 0, 'C', $fill);
                $pdf->Cell($w[3], 6, $risk_allele, 1, 0, 'C', $fill);
                $pdf->Cell($w[4], 6, $impact, 1, 0, 'C', true); // Always show impact with color
                $pdf->Cell($w[5], 6, $pathway, 1, 1, 'L', $fill);
                
                $fill = !$fill; // Alternate row colors
            }
            
            $pdf->Ln(10);
            
            // Legend
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'Legend', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 6, '+/+: Two copies of risk allele (High risk)', 0, 1, 'L');
            $pdf->Cell(0, 6, '+/-: One copy of risk allele (Medium risk)', 0, 1, 'L');
            $pdf->Cell(0, 6, '-/-: No copies of risk allele (Low risk)', 0, 1, 'L');
            $pdf->Ln(10);
            
            // Disclaimer
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'Important Disclaimer', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 8);
            
            $disclaimer = "This report is for educational and informational purposes only. It is not intended to diagnose, treat, cure, or prevent any disease. The genetic variants analyzed represent only a small portion of your genetic makeup. Please consult with a qualified healthcare professional before making any decisions based on this information. Genetic testing has limitations and this report should not be used as a substitute for professional medical advice.";
            
            $pdf->MultiCell(0, 5, $disclaimer, 0, 'J');
            $pdf->Ln(10);
            
            // Footer info
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->Cell(0, 5, 'Generated by MTHFRSupport.org | Report Version 2.5 | ' . date('Y-m-d'), 0, 1, 'C');
            
            // Output PDF as string
            return $pdf->Output('', 'S');
            
        } catch (Exception $e) {
            GRG_Logger::log('error', "TCPDF generation failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generate simple PDF using FPDF (fallback)
     */
    public static function generate_fpdf_report($genetic_data, $report_type, $product_name, $upload_id, $order_id) {
        if (!class_exists('FPDF')) {
            return null;
        }
        
        try {
            $pdf = new FPDF();
            $pdf->AddPage();
            
            // Title
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, "MTHFRSupport $report_type Genetic Report", 0, 1, 'C');
            $pdf->Ln(5);
            
            // Basic info
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(40, 8, 'Product: ', 0, 0);
            $pdf->Cell(0, 8, $product_name, 0, 1);
            $pdf->Cell(40, 8, 'Report ID: ', 0, 0);
            $pdf->Cell(0, 8, "$upload_id-$order_id", 0, 1);
            $pdf->Cell(40, 8, 'Generated: ', 0, 0);
            $pdf->Cell(0, 8, date('Y-m-d H:i'), 0, 1);
            $pdf->Cell(40, 8, 'Total Variants: ', 0, 0);
            $pdf->Cell(0, 8, count($genetic_data), 0, 1);
            $pdf->Ln(10);
            
            // Simple table
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(30, 8, 'SNP ID', 1, 0, 'C');
            $pdf->Cell(40, 8, 'Your Alleles', 1, 0, 'C');
            $pdf->Cell(30, 8, 'Risk Allele', 1, 0, 'C');
            $pdf->Cell(20, 8, 'Result', 1, 1, 'C');
            
            // Data rows
            $pdf->SetFont('Arial', '', 9);
            foreach ($genetic_data as $variant) {
                $rsid = $variant['rsid'];
                $genotype = $variant['genotype'];
                $risk_allele = $variant['risk_allele'];
                
                // Calculate result
                $risk_count = substr_count($genotype, $risk_allele);
                if ($risk_count >= 2) {
                    $result = "+/+";
                } elseif ($risk_count === 1) {
                    $result = "+/-";
                } else {
                    $result = "-/-";
                }
                
                $pdf->Cell(30, 6, $rsid, 1, 0, 'C');
                $pdf->Cell(40, 6, $genotype, 1, 0, 'C');
                $pdf->Cell(30, 6, $risk_allele, 1, 0, 'C');
                $pdf->Cell(20, 6, $result, 1, 1, 'C');
            }
            
            $pdf->Ln(10);
            
            // Footer
            $pdf->SetFont('Arial', 'I', 8);
            $pdf->Cell(0, 5, 'Generated by MTHFRSupport.org - For educational purposes only', 0, 1, 'C');
            
            return $pdf->Output('', 'S');
            
        } catch (Exception $e) {
            GRG_Logger::log('error', "FPDF generation failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generate HTML-based PDF (using dompdf or similar)
     */
    public static function generate_html_pdf($genetic_data, $report_type, $product_name, $upload_id, $order_id) {
        try {
            $html = self::generate_report_html($genetic_data, $report_type, $product_name, $upload_id, $order_id);
            
            // Check if dompdf is available
            if (class_exists('Dompdf\Dompdf')) {
                return self::generate_dompdf($html);
            }
            
            // Check if mpdf is available
            if (class_exists('Mpdf\Mpdf')) {
                return self::generate_mpdf($html);
            }
            
            // Fallback: save as HTML (not ideal but functional)
            return $html;
            
        } catch (Exception $e) {
            GRG_Logger::log('error', "HTML PDF generation failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generate PDF using dompdf
     */
    private static function generate_dompdf($html) {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }
    
    /**
     * Generate PDF using mpdf
     */
    private static function generate_mpdf($html) {
        $mpdf = new \Mpdf\Mpdf();
        $mpdf->WriteHTML($html);
        return $mpdf->Output('', 'S');
    }
    
    /**
     * Generate HTML content for PDF
     */
    private static function generate_report_html($genetic_data, $report_type, $product_name, $upload_id, $order_id) {
        $target_snps = self::get_target_snps();
        
        // Count results
        $result_counts = array('+/+' => 0, '+/-' => 0, '-/-' => 0);
        foreach ($genetic_data as $variant) {
            $risk_count = substr_count($variant['genotype'], $variant['risk_allele']);
            if ($risk_count >= 2) {
                $result_counts['+/+']++;
            } elseif ($risk_count === 1) {
                $result_counts['+/-']++;
            } else {
                $result_counts['-/-']++;
            }
        }
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>MTHFRSupport <?php echo $report_type; ?> Genetic Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .title { font-size: 18px; font-weight: bold; color: #1e3a8a; }
                .info-section { margin: 20px 0; }
                .info-row { margin: 5px 0; }
                .section-title { font-size: 14px; font-weight: bold; margin: 20px 0 10px 0; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th { background-color: #1e3a8a; color: white; padding: 8px; text-align: center; }
                td { padding: 6px; text-align: center; border: 1px solid #ddd; }
                .high-risk { background-color: #ffcccc; }
                .medium-risk { background-color: #ffffcc; }
                .low-risk { background-color: #ccffcc; }
                .disclaimer { font-size: 10px; margin-top: 30px; text-align: justify; }
                .footer { font-size: 10px; text-align: center; margin-top: 20px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title">MTHFRSupport <?php echo $report_type; ?> Genetic Report</div>
            </div>
            
            <div class="info-section">
                <div class="info-row"><strong>Product:</strong> <?php echo htmlspecialchars($product_name); ?></div>
                <div class="info-row"><strong>Report ID:</strong> <?php echo $upload_id; ?>-<?php echo $order_id; ?></div>
                <div class="info-row"><strong>Generated:</strong> <?php echo date('F j, Y g:i A'); ?></div>
                <div class="info-row"><strong>Total Variants:</strong> <?php echo count($genetic_data); ?></div>
            </div>
            
            <div class="section-title">Report Summary</div>
            <div class="info-section">
                <div class="info-row"><strong>High Risk (+/+):</strong> <?php echo $result_counts['+/+']; ?> variants</div>
                <div class="info-row"><strong>Medium Risk (+/-):</strong> <?php echo $result_counts['+/-']; ?> variants</div>
                <div class="info-row"><strong>Low Risk (-/-):</strong> <?php echo $result_counts['-/-']; ?> variants</div>
            </div>
            
            <div class="section-title">Detailed Genetic Analysis</div>
            <table>
                <thead>
                    <tr>
                        <th>SNP ID</th>
                        <th>Gene/Variant</th>
                        <th>Your Result</th>
                        <th>Risk Allele</th>
                        <th>Impact</th>
                        <th>Pathway</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($genetic_data as $variant): 
                        $rsid = $variant['rsid'];
                        $genotype = $variant['genotype'];
                        $risk_allele = $variant['risk_allele'];
                        
                        // Calculate result and impact
                        $risk_count = substr_count($genotype, $risk_allele);
                        if ($risk_count >= 2) {
                            $result = "+/+";
                            $impact = "High";
                            $class = "high-risk";
                        } elseif ($risk_count === 1) {
                            $result = "+/-";
                            $impact = "Medium";
                            $class = "medium-risk";
                        } else {
                            $result = "-/-";
                            $impact = "Low";
                            $class = "low-risk";
                        }
                        
                        $snp_info = isset($target_snps[$rsid]) ? $target_snps[$rsid] : array(
                            'name' => $rsid,
                            'pathway' => 'Other'
                        );
                    ?>
                    <tr>
                        <td><?php echo $rsid; ?></td>
                        <td><?php echo htmlspecialchars($snp_info['name']); ?></td>
                        <td><?php echo $genotype; ?></td>
                        <td><?php echo $risk_allele; ?></td>
                        <td class="<?php echo $class; ?>"><?php echo $impact; ?></td>
                        <td><?php echo htmlspecialchars($snp_info['pathway']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="section-title">Legend</div>
            <div class="info-section">
                <div class="info-row"><strong>+/+:</strong> Two copies of risk allele (High risk)</div>
                <div class="info-row"><strong>+/-:</strong> One copy of risk allele (Medium risk)</div>
                <div class="info-row"><strong>-/-:</strong> No copies of risk allele (Low risk)</div>
            </div>
            
            <div class="disclaimer">
                <strong>Important Disclaimer:</strong> This report is for educational and informational purposes only. It is not intended to diagnose, treat, cure, or prevent any disease. The genetic variants analyzed represent only a small portion of your genetic makeup. Please consult with a qualified healthcare professional before making any decisions based on this information. Genetic testing has limitations and this report should not be used as a substitute for professional medical advice.
            </div>
            
            <div class="footer">
                Generated by MTHFRSupport.org | Report Version 2.5 | <?php echo date('Y-m-d'); ?>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get target SNPs (same as in main generator)
     */
    private static function get_target_snps() {
        return array(
            // MTHFR and Methylation Pathway
            'rs1801133' => array(
                'name' => 'MTHFR C677T',
                'pathway' => 'Methylation & Methionine/Homocysteine Pathways',
                'tags' => 'MTHFR, methylation, folate, homocysteine, cardiovascular, neural tube defects, thermolabile',
                'risk_allele' => 'T'
            ),
            'rs1801131' => array(
                'name' => 'MTHFR A1298C',
                'pathway' => 'Methylation & Methionine/Homocysteine Pathways',
                'tags' => 'MTHFR, methylation, folate, BH4, neurotransmitter, mood',
                'risk_allele' => 'C'
            ),
            'rs1805087' => array(
                'name' => 'MTR A2756G',
                'pathway' => 'Methylation & Methionine/Homocysteine Pathways',
                'tags' => 'MTR, methylation, B12, homocysteine, methionine synthase',
                'risk_allele' => 'G'
            ),
            'rs1801394' => array(
                'name' => 'MTRR A66G',
                'pathway' => 'Methylation & Methionine/Homocysteine Pathways',
                'tags' => 'MTRR, methylation, B12, homocysteine, methionine synthase reductase',
                'risk_allele' => 'G'
            ),
            'rs234715' => array(
                'name' => 'BHMT R239Q',
                'pathway' => 'Trans-sulfuration Pathway',
                'tags' => 'BHMT, betaine, homocysteine, choline, alternative methylation',
                'risk_allele' => 'T'
            ),
            
            // Detoxification Pathways
            'rs662' => array(
                'name' => 'PON1 Q192R',
                'pathway' => 'Liver Detox - Phase I',
                'tags' => 'PON1, detoxification, organophosphate, pesticides, paraoxonase',
                'risk_allele' => 'G'
            ),
            'rs1695' => array(
                'name' => 'GSTP1 I105V',
                'pathway' => 'Liver Detox - Phase II',
                'tags' => 'GSTP1, glutathione, phase II detox, conjugation, xenobiotic metabolism',
                'risk_allele' => 'G'
            ),
            'rs4244285' => array(
                'name' => 'CYP2C19*2',
                'pathway' => 'Liver Detox - Phase I',
                'tags' => 'CYP2C19, drug metabolism, proton pump inhibitors, poor metabolizer',
                'risk_allele' => 'G'
            ),
            
            // Neurotransmitter Pathways
            'rs4680' => array(
                'name' => 'COMT V158M',
                'pathway' => 'COMT Activity',
                'tags' => 'COMT, dopamine, catecholamine, neurotransmitter, executive function, warrior worrier',
                'risk_allele' => 'G'
            ),
            'rs6323' => array(
                'name' => 'MAOA T297C',
                'pathway' => 'Serotonin & Dopamine',
                'tags' => 'MAOA, serotonin, dopamine, neurotransmitter metabolism, warrior gene',
                'risk_allele' => 'T'
            ),
            
            // Alcohol Metabolism
            'rs2238151' => array(
                'name' => 'ALDH2 E487K',
                'pathway' => 'Yeast/Alcohol Metabolism',
                'tags' => 'ALDH2, alcohol metabolism, acetaldehyde, ethanol, hangover, Asian flush',
                'risk_allele' => 'T'
            ),
            
            // COVID and HLA variants
            'rs2070788' => array(
                'name' => 'HLA-DQA1',
                'pathway' => 'HLA',
                'tags' => 'HLA, immune response, MHC class II, antigen presentation, COVID-19',
                'risk_allele' => 'G'
            ),
            'rs4343' => array(
                'name' => 'ACE I/D',
                'pathway' => 'HLA',
                'tags' => 'ACE, angiotensin converting enzyme, COVID-19, cardiovascular, hypertension',
                'risk_allele' => 'G'
            )
        );
    }
}