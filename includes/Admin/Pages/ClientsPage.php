<?php
/**
 * Clients Page
 * 
 * Gestione clienti (CRM)
 */

namespace FP\FinanceHub\Admin\Pages;

use FP\FinanceHub\Services\ClientService;
use FP\FinanceHub\Database\Models\Client as ClientModel;

if (!defined('ABSPATH')) {
    exit;
}

class ClientsPage {
    
    /**
     * Render pagina clienti
     */
    public static function render() {
        $client_service = ClientService::get_instance();
        
        // Gestione azioni
        if (isset($_POST['action'])) {
            self::handle_action($_POST['action']);
        }
        
        // Ottieni clienti
        $page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        $clients = $client_service->get_all([
            'page' => $page,
            'per_page' => 20,
            'search' => $search,
        ]);
        
        $total = ClientModel::count(['search' => $search]);
        
        ?>
        <div class="wrap fp-fh-wrapper">
            <div class="fp-fh-header">
                <div class="fp-fh-header-title">
                    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                    <p>Gestisci la tua anagrafica clienti</p>
                </div>
                <div class="fp-fh-header-actions">
                    <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-clients&action=add'); ?>" class="fp-fh-btn fp-fh-btn-primary">
                        ➕ Aggiungi Cliente
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-import&tab=aruba'); ?>" class="fp-fh-btn fp-fh-btn-outline">
                        🔗 Import da Aruba
                    </a>
                </div>
            </div>
            
            <?php if (!empty($search)) : ?>
                <div class="fp-clients-filters">
                    <div class="fp-clients-search">
                        <input type="text" class="fp-fh-input" value="<?php echo esc_attr($search); ?>" placeholder="Cerca clienti..." readonly />
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Lista clienti -->
            <div class="fp-fh-table-wrapper">
                <?php if (empty($clients)) : ?>
                    <div class="fp-clients-empty">
                        <div class="fp-clients-empty-icon">👥</div>
                        <h3 class="fp-clients-empty-title">Nessun cliente trovato</h3>
                        <p class="fp-clients-empty-text">Inizia aggiungendo il tuo primo cliente o importa i dati da Aruba.</p>
                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-clients&action=add'); ?>" class="fp-fh-btn fp-fh-btn-primary">
                            ➕ Aggiungi Cliente
                        </a>
                    </div>
                <?php else : ?>
                    <!-- Desktop Table View -->
                    <table class="fp-fh-table fp-fh-table-striped fp-fh-table-desktop">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>P.IVA</th>
                                <th>Email</th>
                                <th>Telefono</th>
                                <th>Fonte</th>
                                <th class="fp-fh-table-actions">Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client) : ?>
                                <tr class="fp-fh-swipeable">
                                    <td><strong><?php echo esc_html($client->name); ?></strong></td>
                                    <td><?php echo esc_html($client->vat_number ?: '-'); ?></td>
                                    <td><?php echo esc_html($client->email ?: '-'); ?></td>
                                    <td><?php echo esc_html($client->phone ?: '-'); ?></td>
                                    <td><span class="fp-fh-badge fp-fh-badge-soft-info"><?php echo esc_html($client->source); ?></span></td>
                                    <td class="fp-fh-table-actions">
                                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-clients&action=edit&id=' . $client->id); ?>" class="fp-fh-btn fp-fh-btn-sm fp-fh-btn-ghost">
                                            Modifica
                                        </a>
                                    </td>
                                    <div class="fp-fh-swipe-actions">
                                        <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-clients&action=edit&id=' . $client->id); ?>" class="fp-fh-btn fp-fh-btn-sm fp-fh-btn-primary">
                                            Modifica
                                        </a>
                                    </div>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Mobile Card View -->
                    <div class="fp-fh-table-mobile-cards">
                        <?php foreach ($clients as $client) : ?>
                            <div class="fp-fh-mobile-card fp-fh-swipeable">
                                <div class="fp-fh-mobile-card-header">
                                    <h3 class="fp-fh-mobile-card-title"><?php echo esc_html($client->name); ?></h3>
                                    <span class="fp-fh-badge fp-fh-badge-soft-info"><?php echo esc_html($client->source); ?></span>
                                </div>
                                <div class="fp-fh-mobile-card-body">
                                    <div class="fp-fh-mobile-card-field">
                                        <span class="fp-fh-mobile-card-label">P.IVA</span>
                                        <span class="fp-fh-mobile-card-value"><?php echo esc_html($client->vat_number ?: '-'); ?></span>
                                    </div>
                                    <div class="fp-fh-mobile-card-field">
                                        <span class="fp-fh-mobile-card-label">Email</span>
                                        <span class="fp-fh-mobile-card-value"><?php echo esc_html($client->email ?: '-'); ?></span>
                                    </div>
                                    <div class="fp-fh-mobile-card-field">
                                        <span class="fp-fh-mobile-card-label">Telefono</span>
                                        <span class="fp-fh-mobile-card-value"><?php echo esc_html($client->phone ?: '-'); ?></span>
                                    </div>
                                </div>
                                <div class="fp-fh-mobile-card-actions">
                                    <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-clients&action=edit&id=' . $client->id); ?>" class="fp-fh-btn fp-fh-btn-primary">
                                        Modifica
                                    </a>
                                </div>
                                <div class="fp-fh-swipe-actions">
                                    <a href="<?php echo admin_url('admin.php?page=fp-finance-hub-clients&action=edit&id=' . $client->id); ?>" class="fp-fh-btn fp-fh-btn-sm fp-fh-btn-primary">
                                        Modifica
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
    
    /**
     * Gestisce azioni form
     */
    private static function handle_action($action) {
        check_admin_referer('fp_finance_hub_clients');
        
        switch ($action) {
            case 'delete':
                if (isset($_POST['client_id'])) {
                    $client_service = ClientService::get_instance();
                    $client_service->delete(absint($_POST['client_id']));
                }
                break;
        }
    }
}
