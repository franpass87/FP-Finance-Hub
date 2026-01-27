<?php
/**
 * Invoices Page
 * 
 * Gestione fatture
 */

namespace FP\FinanceHub\Admin\Pages;

use FP\FinanceHub\Services\InvoiceService;
use FP\FinanceHub\Database\Models\Client as ClientModel;

if (!defined('ABSPATH')) {
    exit;
}

class InvoicesPage {
    
    /**
     * Render pagina fatture
     */
    public static function render() {
        $invoice_service = InvoiceService::get_instance();
        
        $unpaid_invoices = $invoice_service->get_unpaid();
        $potential_income = $invoice_service->calculate_potential_income();
        
        ?>
        <div class="wrap fp-fh-wrapper">
            <div class="fp-fh-header">
                <div class="fp-fh-header-title">
                    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                    <p>Gestisci le fatture emesse e monitora i pagamenti</p>
                </div>
            </div>
            
            <div class="fp-invoices-summary">
                <div class="fp-invoice-summary-card fp-fh-card">
                    <div class="fp-invoice-summary-label">💰 Potenziale Entrate</div>
                    <div class="fp-invoice-summary-value fp-financial-amount fp-financial-amount-positive">
                        <?php echo esc_html(number_format($potential_income, 2, ',', '.') . ' €'); ?>
                    </div>
                </div>
                <div class="fp-invoice-summary-card fp-fh-card">
                    <div class="fp-invoice-summary-label">📄 Fatture Non Pagate</div>
                    <div class="fp-invoice-summary-value"><?php echo count($unpaid_invoices); ?></div>
                </div>
            </div>
            
            <!-- Lista fatture -->
            <div class="fp-fh-table-wrapper">
                <?php if (empty($unpaid_invoices)) : ?>
                    <div class="fp-fh-card">
                        <div class="fp-fh-card-body fp-fh-text-center fp-fh-p-8">
                            <div style="font-size: 4rem; margin-bottom: var(--fp-fh-spacing-4); opacity: 0.5;">📄</div>
                            <h3 class="fp-clients-empty-title">Nessuna fattura non pagata</h3>
                            <p class="fp-clients-empty-text">Tutte le fatture sono state pagate. Ottimo lavoro!</p>
                        </div>
                    </div>
                <?php else : ?>
                    <!-- Desktop Table View -->
                    <table class="fp-fh-table fp-fh-table-striped fp-fh-table-desktop">
                        <thead>
                            <tr>
                                <th>Numero</th>
                                <th>Cliente</th>
                                <th>Data Emissione</th>
                                <th>Importo</th>
                                <th>Stato</th>
                                <th>Stato Aruba</th>
                                <th class="fp-fh-table-actions">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unpaid_invoices as $invoice) : ?>
                                <tr class="fp-fh-swipeable">
                                    <td><strong><?php echo esc_html($invoice->invoice_number); ?></strong></td>
                                    <td><?php 
                                        if ($invoice->client_id) {
                                            $client = ClientModel::get($invoice->client_id);
                                            echo esc_html($client ? $client->name : '-');
                                        } else {
                                            echo '-';
                                        }
                                    ?></td>
                                    <td><?php echo esc_html($invoice->issue_date); ?></td>
                                    <td class="fp-financial-amount"><?php echo esc_html(number_format($invoice->total_amount, 2, ',', '.') . ' €'); ?></td>
                                    <td>
                                        <span class="fp-fh-badge fp-fh-badge-status-<?php echo esc_attr(strtolower($invoice->status)); ?>">
                                            <?php echo esc_html($invoice->status); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $invoice->aruba_status ? '<span class="fp-fh-badge fp-fh-badge-soft-info">' . esc_html($invoice->aruba_status) . '</span>' : '-'; ?></td>
                                    <td class="fp-fh-table-actions">
                                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-invoices&action=view&id=' . $invoice->id); ?>" class="fp-fh-btn fp-fh-btn-sm fp-fh-btn-ghost">
                                            Dettagli
                                        </a>
                                    </td>
                                    <div class="fp-fh-swipe-actions">
                                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-invoices&action=view&id=' . $invoice->id); ?>" class="fp-fh-btn fp-fh-btn-sm fp-fh-btn-primary">
                                            Dettagli
                                        </a>
                                    </div>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Mobile Card View -->
                    <div class="fp-fh-table-mobile-cards">
                        <?php foreach ($unpaid_invoices as $invoice) : 
                            $client = $invoice->client_id ? ClientModel::get($invoice->client_id) : null;
                        ?>
                            <div class="fp-fh-mobile-card fp-fh-swipeable">
                                <div class="fp-fh-mobile-card-header">
                                    <h3 class="fp-fh-mobile-card-title"><?php echo esc_html($invoice->invoice_number); ?></h3>
                                    <span class="fp-fh-badge fp-fh-badge-status-<?php echo esc_attr(strtolower($invoice->status)); ?>">
                                        <?php echo esc_html($invoice->status); ?>
                                    </span>
                                </div>
                                <div class="fp-fh-mobile-card-body">
                                    <div class="fp-fh-mobile-card-field">
                                        <span class="fp-fh-mobile-card-label">Cliente</span>
                                        <span class="fp-fh-mobile-card-value"><?php echo esc_html($client ? $client->name : '-'); ?></span>
                                    </div>
                                    <div class="fp-fh-mobile-card-field">
                                        <span class="fp-fh-mobile-card-label">Data Emissione</span>
                                        <span class="fp-fh-mobile-card-value"><?php echo esc_html($invoice->issue_date); ?></span>
                                    </div>
                                    <div class="fp-fh-mobile-card-field">
                                        <span class="fp-fh-mobile-card-label">Importo</span>
                                        <span class="fp-fh-mobile-card-value fp-financial-amount"><?php echo esc_html(number_format($invoice->total_amount, 2, ',', '.') . ' €'); ?></span>
                                    </div>
                                    <?php if ($invoice->aruba_status) : ?>
                                    <div class="fp-fh-mobile-card-field">
                                        <span class="fp-fh-mobile-card-label">Stato Aruba</span>
                                        <span class="fp-fh-mobile-card-value">
                                            <span class="fp-fh-badge fp-fh-badge-soft-info"><?php echo esc_html($invoice->aruba_status); ?></span>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="fp-fh-mobile-card-actions">
                                    <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-invoices&action=view&id=' . $invoice->id); ?>" class="fp-fh-btn fp-fh-btn-primary">
                                        Dettagli
                                    </a>
                                </div>
                                <div class="fp-fh-swipe-actions">
                                    <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-invoices&action=view&id=' . $invoice->id); ?>" class="fp-fh-btn fp-fh-btn-sm fp-fh-btn-primary">
                                        Dettagli
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
