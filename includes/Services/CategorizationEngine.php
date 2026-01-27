<?php
/**
 * Categorization Engine
 * 
 * Categorizzazione intelligente movimenti bancari
 */

namespace FP\FinanceHub\Services;

use FP\FinanceHub\Database\Models\CategorizationLearning;
use FP\FinanceHub\Database\Models\CategorizationRule;
use FP\FinanceHub\Database\Models\Transaction as TransactionModel;

if (!defined('ABSPATH')) {
    exit;
}

class CategorizationEngine {
    
    private static $instance = null;
    
    private $keyword_dict = [];
    private $stop_words = [];
    
    // Cache pattern appresi
    private $learned_patterns_cache = null;
    private $learned_rules_cache = null;
    
    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Categorizza un movimento bancario
     */
    public function categorize($transaction) {
        // 1. Normalizza descrizione
        $normalized = $this->normalize_description($transaction->description ?? '');
        
        // 2. Cerca prima nelle regole apprese (tabelle database)
        $learned_match = $this->match_learned_rules($normalized, $transaction);
        if ($learned_match && $learned_match['confidence'] >= 0.7) {
            return $learned_match;
        }
        
        // 3. Pattern matching keywords (fuzzy se nessun match diretto)
        $keyword_match = $this->match_keywords_intelligent($normalized);
        
        // 4. Machine learning prediction (se disponibile)
        $ml_match = $this->ml_predict($normalized, $transaction);
        if ($ml_match && $ml_match['confidence'] >= 0.8) {
            return $ml_match;
        }
        
        // 5. Selezione categoria finale
        if ($keyword_match) {
            // Rileva tipo business/personal intelligente
            $transaction_type = $this->detect_transaction_type_intelligent(
                $normalized,
                floatval($transaction->amount ?? 0),
                $transaction
            );
            
            return [
                'category' => $keyword_match['category'],
                'subcategory' => $keyword_match['subcategory'] ?? null,
                'transaction_type' => $transaction_type,
                'is_personal' => $transaction_type === 'personal',
                'is_business' => $transaction_type === 'business',
                'confidence' => $keyword_match['confidence'],
                'method' => 'keyword',
                'matched_keywords' => $keyword_match['matched_keywords'] ?? [],
            ];
        }
        
        // Usa learned match anche se confidence più bassa come fallback
        if ($learned_match) {
            return $learned_match;
        }
        
        // Default: non categorizzato
        return [
            'category' => null,
            'subcategory' => null,
            'transaction_type' => 'unknown',
            'is_personal' => false,
            'is_business' => false,
            'confidence' => 0.0,
            'method' => 'none',
        ];
    }
    
    /**
     * Normalizza descrizione movimento
     */
    private function normalize_description($description) {
        // Lowercase
        $text = mb_strtolower($description, 'UTF-8');
        
        // Rimuovi caratteri speciali ridondanti
        $text = preg_replace('/[^\w\s]/u', ' ', $text);
        
        // Rimuovi stop words
        $text = $this->remove_stop_words($text);
        
        // Rimuovi spazi multipli
        $text = preg_replace('/\s+/', ' ', trim($text));
        
        return $text;
    }
    
    /**
     * Rimuovi stop words comuni
     */
    private function remove_stop_words($text) {
        $stop_words = [
            'pagamento', 'bonifico', 'ricevuto', 'effettuato',
            's.p.a.', 'srl', 'spa', 's.r.l.',
            'bolletta', 'n.', 'nr', 'numero',
            'via', 'viale', 'piazza', 'corso',
        ];
        
        $words = explode(' ', $text);
        $filtered = array_filter($words, function($word) use ($stop_words) {
            return !in_array($word, $stop_words);
        });
        
        return implode(' ', $filtered);
    }
    
    /**
     * Match keywords nel dizionario
     */
    private function match_keywords($text) {
        $matches = [];
        $keyword_dict = $this->get_keyword_dict();
        
        foreach ($keyword_dict as $category => $keywords) {
            $score = 0;
            $matched_keywords = [];
            
            foreach ($keywords as $keyword => $config) {
                $kw = is_array($config) ? $keyword : $config;
                $subcat = is_array($config) ? ($config['subcategory'] ?? null) : null;
                
                if (stripos($text, $kw) !== false) {
                    $score += 1;
                    $matched_keywords[] = $kw;
                    
                    // Se ha sottocategoria, salvala
                    if ($subcat && !isset($matches[$category]['subcategory'])) {
                        $matches[$category]['subcategory'] = $subcat;
                    }
                }
            }
            
            if ($score > 0) {
                $total_keywords = is_array($keywords) ? count($keywords) : 1;
                $category_data = [
                    'score' => $score / max($total_keywords, 1),
                    'matched_keywords' => $matched_keywords,
                ];
                // Aggiungi subcategory se salvata precedentemente
                if (isset($matches[$category]['subcategory'])) {
                    $category_data['subcategory'] = $matches[$category]['subcategory'];
                }
                $matches[$category] = $category_data;
            }
        }
        
        // Ritorna match con punteggio più alto
        if (!empty($matches)) {
            uasort($matches, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });
            
            $best = array_key_first($matches);
            return [
                'category' => $best,
                'subcategory' => $matches[$best]['subcategory'] ?? null,
                'confidence' => min($matches[$best]['score'], 1.0),
                'matched_keywords' => $matches[$best]['matched_keywords'],
            ];
        }
        
        return null;
    }
    
    /**
     * Rileva tipo transazione (business/personal) - VERSIONE ORIGINALE (backward compatibility)
     */
    private function detect_transaction_type($description, $amount) {
        return $this->detect_transaction_type_intelligent($description, $amount, null);
    }
    
    /**
     * FASE 2.1: Rileva tipo transazione intelligente con analisi multi-fattore
     */
    private function detect_transaction_type_intelligent($description, $amount, $transaction = null) {
        $business_score = 0;
        $personal_score = 0;
        
        // 1. Pattern descrizione - Keywords business vs personal
        $business_keywords = [
            'fattura', 'invoice', 'cliente', 'fornitore', 'srl', 's.p.a.', 
            'ricavi', 'fatturazione', 'pagamento cliente', 'accredito cliente',
            'p.iva', 'partita iva', 'ragione sociale', 'azienda', 'società'
        ];
        
        $personal_keywords = [
            'supermercato', 'coop', 'conad', 'esselunga', 'farmacia', 
            'benzina', 'alimentari', 'utenze', 'enel', 'tim', 'vodafone'
        ];
        
        $business_keyword_matches = $this->count_matches($description, $business_keywords);
        $personal_keyword_matches = $this->count_matches($description, $personal_keywords);
        
        $business_score += $business_keyword_matches * 2;
        $personal_score += $personal_keyword_matches * 2;
        
        // 2. Analisi statistica importi
        if ($transaction) {
            $stats = $this->get_category_statistics($transaction->category ?? null);
            
            if ($stats) {
                // Se importo molto più alto della media business → business
                if ($stats['business_avg'] > 0 && $amount > ($stats['business_avg'] * 1.5)) {
                    $business_score += 3;
                }
                
                // Se importo nella media personal → personal
                if ($stats['personal_avg'] > 0 && abs($amount) >= ($stats['personal_avg'] * 0.5) && abs($amount) <= ($stats['personal_avg'] * 1.5)) {
                    $personal_score += 2;
                }
            }
        }
        
        // 3. Pattern importo
        // Importi molto alti (>1000€) spesso business
        if (abs($amount) > 1000) {
            if (stripos($description, 'fattura') !== false || $business_keyword_matches > 0) {
                $business_score += 3;
            } else {
                $business_score += 1; // Leggero bonus per importi alti
            }
        }
        
        // Importi multipli di 100 spesso business (fatture)
        if (abs($amount) > 0 && abs($amount) % 100 < 5) {
            $business_score += 1;
        }
        
        // 4. Orario transazione (se disponibile)
        if ($transaction && isset($transaction->transaction_date)) {
            $hour = (int)date('H', strtotime($transaction->transaction_date));
            // Orari lavorativi (9-18) tendono a essere business
            if ($hour >= 9 && $hour <= 18) {
                $business_score += 0.5;
            } else {
                // Fuori orari lavorativi spesso personal
                $personal_score += 0.5;
            }
        }
        
        // 5. Pattern frequenza (se stesso fornitore/cliente ricorrente → business)
        if ($transaction) {
            $recurrence = $this->check_recurrence_pattern($description, $amount);
            if ($recurrence['is_recurring']) {
                if ($recurrence['type'] === 'business') {
                    $business_score += 2;
                } else {
                    $personal_score += 2; // Utenze ricorrenti = personal
                }
            }
        }
        
        // 6. Pattern P.IVA o ragione sociale → business
        if (preg_match('/p\.?\s*iva|partita\s+iva|s\.?r\.?l\.?|s\.?p\.?a\.?/i', $description)) {
            $business_score += 3;
        }
        
        // Decisione finale
        if ($business_score > $personal_score) {
            return 'business';
        } elseif ($personal_score > $business_score) {
            return 'personal';
        }
        
        // Default: se importo alto e contiene "fattura" → business
        if (abs($amount) > 1000 && stripos($description, 'fattura') !== false) {
            return 'business';
        }
        
        // Default: personal per importi bassi
        return 'personal';
    }
    
    /**
     * Ottieni statistiche categoria per analisi importi
     */
    private function get_category_statistics($category_id) {
        if (!$category_id) {
            return null;
        }
        
        global $wpdb;
        $transactions_table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                AVG(CASE WHEN is_business = 1 THEN ABS(amount) ELSE 0 END) as business_avg,
                AVG(CASE WHEN is_personal = 1 THEN ABS(amount) ELSE 0 END) as personal_avg,
                STDDEV(CASE WHEN is_business = 1 THEN ABS(amount) ELSE 0 END) as business_std,
                STDDEV(CASE WHEN is_personal = 1 THEN ABS(amount) ELSE 0 END) as personal_std
            FROM {$transactions_table}
            WHERE category_id = %d
            AND (is_business = 1 OR is_personal = 1)",
            $category_id
        ));
        
        if (!$stats) {
            return null;
        }
        
        return [
            'business_avg' => floatval($stats->business_avg ?? 0),
            'personal_avg' => floatval($stats->personal_avg ?? 0),
            'business_std' => floatval($stats->business_std ?? 0),
            'personal_std' => floatval($stats->personal_std ?? 0),
        ];
    }
    
    /**
     * Verifica pattern di ricorrenza
     */
    private function check_recurrence_pattern($description, $amount) {
        global $wpdb;
        $transactions_table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        // Estrai nome fornitore/cliente (prima parte della descrizione)
        $parts = explode(' ', $description);
        $merchant_name = implode(' ', array_slice($parts, 0, 2)); // Prime 2 parole
        
        if (strlen($merchant_name) < 4) {
            return ['is_recurring' => false];
        }
        
        // Conta occorrenze dello stesso merchant con importo simile
        $similar_amount = $amount * 0.9; // ±10% tolleranza
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
            FROM {$transactions_table}
            WHERE description LIKE %s
            AND ABS(ABS(amount) - ABS(%f)) <= ABS(%f) * 0.1
            AND transaction_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)",
            '%' . $wpdb->esc_like($merchant_name) . '%',
            abs($amount),
            abs($amount)
        ));
        
        $is_recurring = $count >= 3; // Almeno 3 occorrenze negli ultimi 6 mesi
        
        // Pattern utenze (ricorrenti mensili) → personal
        $utilities_patterns = ['enel', 'tim', 'vodafone', 'fastweb', 'acqua', 'gas', 'luce'];
        $is_utility = false;
        foreach ($utilities_patterns as $pattern) {
            if (stripos($description, $pattern) !== false) {
                $is_utility = true;
                break;
            }
        }
        
        return [
            'is_recurring' => $is_recurring,
            'count' => intval($count),
            'type' => $is_utility ? 'personal' : 'business',
        ];
    }
    
    /**
     * FASE 2.2: Machine Learning prediction (Naive Bayes semplificato)
     */
    private function ml_predict($normalized, $transaction) {
        // Estrai features
        $features = [
            'keywords' => explode(' ', $normalized),
            'amount' => floatval($transaction->amount ?? 0),
            'amount_abs' => abs(floatval($transaction->amount ?? 0)),
            'description' => $normalized,
        ];
        
        // Calcola probabilità business vs personal basata su pattern storici
        $business_prob = $this->calculate_business_probability_naive_bayes($features, $transaction);
        $personal_prob = 1 - $business_prob;
        
        if ($business_prob >= 0.8) {
            return [
                'category' => null, // Da determinare separatamente
                'transaction_type' => 'business',
                'is_personal' => false,
                'is_business' => true,
                'confidence' => $business_prob,
                'method' => 'ml_prediction',
            ];
        } elseif ($personal_prob >= 0.8) {
            return [
                'category' => null,
                'transaction_type' => 'personal',
                'is_personal' => true,
                'is_business' => false,
                'confidence' => $personal_prob,
                'method' => 'ml_prediction',
            ];
        }
        
        return null; // Confidence troppo bassa
    }
    
    /**
     * Calcola probabilità business con Naive Bayes semplificato
     */
    private function calculate_business_probability_naive_bayes($features, $transaction) {
        global $wpdb;
        $transactions_table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        // Probabilità a priori P(Business) e P(Personal)
        $total_business = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$transactions_table} WHERE is_business = 1"
        );
        $total_personal = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$transactions_table} WHERE is_personal = 1"
        );
        $total_classified = $total_business + $total_personal;
        
        if ($total_classified == 0) {
            // Nessun dato storico, usa metodo base
            return $this->calculate_business_probability_base($features);
        }
        
        $p_business_prior = $total_business / $total_classified;
        $p_personal_prior = $total_personal / $total_classified;
        
        // Features: keywords
        $keywords = $features['keywords'];
        $p_keywords_business = 1.0;
        $p_keywords_personal = 1.0;
        
        foreach ($keywords as $keyword) {
            if (strlen($keyword) < 4) {
                continue; // Skip parole troppo corte
            }
            
            // Conta occorrenze keyword in business vs personal
            $count_business = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$transactions_table} 
                WHERE is_business = 1 
                AND LOWER(description) LIKE %s",
                '%' . $wpdb->esc_like($keyword) . '%'
            ));
            
            $count_personal = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$transactions_table} 
                WHERE is_personal = 1 
                AND LOWER(description) LIKE %s",
                '%' . $wpdb->esc_like($keyword) . '%'
            ));
            
            // Probabilità condizionale (con Laplace smoothing)
            $p_kw_business = ($count_business + 1) / ($total_business + 2);
            $p_kw_personal = ($count_personal + 1) / ($total_personal + 2);
            
            $p_keywords_business *= $p_kw_business;
            $p_keywords_personal *= $p_kw_personal;
        }
        
        // Feature: importo
        $amount = $features['amount_abs'];
        $p_amount_business = $this->calculate_amount_probability($amount, 'business');
        $p_amount_personal = $this->calculate_amount_probability($amount, 'personal');
        
        // Naive Bayes: P(Business|Features) = P(Features|Business) * P(Business) / P(Features)
        // P(Features) = P(Features|Business) * P(Business) + P(Features|Personal) * P(Personal)
        $p_features_business = $p_keywords_business * $p_amount_business * $p_business_prior;
        $p_features_personal = $p_keywords_personal * $p_amount_personal * $p_personal_prior;
        $p_features = $p_features_business + $p_features_personal;
        
        if ($p_features == 0) {
            return $this->calculate_business_probability_base($features);
        }
        
        $p_business = $p_features_business / $p_features;
        
        return max(0, min(1, $p_business));
    }
    
    /**
     * Calcola probabilità importo per business/personal
     */
    private function calculate_amount_probability($amount, $type) {
        global $wpdb;
        $transactions_table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        // Calcola media e deviazione standard per tipo
        $column = $type === 'business' ? 'is_business' : 'is_personal';
        $stats = $wpdb->get_row(
            "SELECT 
                AVG(ABS(amount)) as avg_amount,
                STDDEV(ABS(amount)) as std_amount
            FROM {$transactions_table}
            WHERE {$column} = 1
            AND amount != 0"
        );
        
        if (!$stats || !$stats->avg_amount) {
            return 0.5; // Default uniforme
        }
        
        $avg = floatval($stats->avg_amount);
        $std = floatval($stats->std_amount) ?: ($avg * 0.5); // Default 50% deviazione
        
        // Probabilità gaussiana (semplificata)
        $diff = abs($amount - $avg);
        $z_score = $std > 0 ? $diff / $std : 0;
        
        // Probabilità basata su z-score (normale standardizzata)
        $probability = exp(-0.5 * pow($z_score, 2));
        
        return max(0.1, min(1.0, $probability));
    }
    
    /**
     * Calcola probabilità business basata su features (metodo base)
     */
    private function calculate_business_probability_base($features) {
        $score = 0.5; // Baseline
        
        // Analizza keywords
        $business_keywords = ['fattura', 'invoice', 'cliente', 'fornitore', 'srl', 'p.iva'];
        $keywords = $features['keywords'];
        
        foreach ($keywords as $keyword) {
            if (in_array($keyword, $business_keywords)) {
                $score += 0.15;
            }
        }
        
        // Analizza importo
        $amount = $features['amount_abs'];
        if ($amount > 1000) {
            $score += 0.2;
        } elseif ($amount < 100) {
            $score -= 0.15; // Importi bassi spesso personal
        }
        
        // Limita tra 0 e 1
        return max(0, min(1, $score));
    }
    
    /**
     * Conta match keyword
     */
    private function count_matches($text, $keywords) {
        $count = 0;
        foreach ($keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $count++;
            }
        }
        return $count;
    }
    
    /**
     * FASE 1.1: Estrae pattern da transazione categorizzata e salva in learning
     */
    public function learn_from_transaction($transaction, $assigned_category_id, $is_business = false) {
        if (empty($transaction->description)) {
            return false;
        }
        
        $normalized = $this->normalize_description($transaction->description);
        
        // Estrai keywords significative
        $keywords = $this->extract_significant_keywords($normalized, $assigned_category_id);
        
        // Salva nella tabella learning
        $learning_id = CategorizationLearning::create([
            'transaction_id' => isset($transaction->id) ? $transaction->id : 0,
            'original_description' => $transaction->description,
            'normalized_description' => $normalized,
            'assigned_category_id' => $assigned_category_id,
            'assigned_by' => 'manual',
            'confidence' => 1.0, // Categorizzazione manuale = confidence 100%
            'keywords_extracted' => $keywords,
        ]);
        
        // Incrementa match count per pattern esistenti simili
        $this->update_similar_patterns($normalized, $assigned_category_id);
        
        // Se confidence alta (>90%), promuovi a regola permanente
        $similar_count = $this->count_similar_patterns($normalized, $assigned_category_id);
        if ($similar_count >= 5) { // Almeno 5 pattern simili
            $this->promote_to_permanent_rule($normalized, $assigned_category_id, $is_business);
        }
        
        // Invalida cache
        $this->learned_patterns_cache = null;
        $this->learned_rules_cache = null;
        
        return $learning_id;
    }
    
    /**
     * FASE 1.2: Match pattern appresi con fuzzy matching
     */
    private function match_learned_rules($normalized, $transaction) {
        // Prima cerca nelle regole permanenti (più veloce)
        $rules = $this->get_learned_rules();
        
        $best_match = null;
        $best_confidence = 0;
        
        foreach ($rules as $rule) {
            $similarity = $this->calculate_similarity($normalized, $rule->pattern);
            
            if ($similarity >= 0.7) { // 70% similarità minima
                $confidence = $similarity * (floatval($rule->confidence ?? 1.0));
                
                // Bonus se match count alto
                if ($rule->match_count > 10) {
                    $confidence = min($confidence * 1.1, 1.0);
                }
                
                if ($confidence > $best_confidence) {
                    $best_confidence = $confidence;
                    $best_match = [
                        'category' => $rule->category_id ?? null,
                        'subcategory' => $rule->subcategory_id ?? null,
                        'transaction_type' => $rule->transaction_type ?? 'personal',
                        'is_personal' => ($rule->transaction_type ?? 'personal') === 'personal',
                        'is_business' => ($rule->transaction_type ?? 'personal') === 'business',
                        'confidence' => $confidence,
                        'method' => 'learned_rule',
                        'matched_pattern' => $rule->pattern ?? '',
                    ];
                }
            }
        }
        
        // Se nessun match diretto, cerca nei pattern learning con fuzzy matching
        if (!$best_match || $best_confidence < 0.8) {
            $similar_patterns = CategorizationLearning::find_similar_patterns($normalized, 5);
            
            if (!empty($similar_patterns)) {
                foreach ($similar_patterns as $pattern) {
                    $similarity = $this->calculate_similarity(
                        $normalized, 
                        $pattern->normalized_description
                    );
                    
                    if ($similarity >= 0.7) {
                        $confidence = $similarity * floatval($pattern->confidence ?? 1.0);
                        
                        if ($confidence > $best_confidence) {
                            $best_confidence = $confidence;
                            $best_match = [
                                'category' => $pattern->assigned_category_id,
                                'subcategory' => null,
                                'transaction_type' => $this->detect_transaction_type_intelligent(
                                    $normalized,
                                    floatval($transaction->amount ?? 0),
                                    $transaction
                                ),
                                'is_personal' => false,
                                'is_business' => false,
                                'confidence' => $confidence,
                                'method' => 'learned_pattern',
                                'matched_pattern' => $pattern->normalized_description,
                            ];
                            
                            $best_match['is_personal'] = $best_match['transaction_type'] === 'personal';
                            $best_match['is_business'] = $best_match['transaction_type'] === 'business';
                        }
                    }
                }
            }
        }
        
        if ($best_match) {
            // Incrementa match count se regola permanente
            $matched_rules = CategorizationRule::find_matching_rules($normalized);
            foreach ($matched_rules as $rule) {
                if ($rule->category_id == $best_match['category']) {
                    CategorizationRule::increment_match_count($rule->id);
                }
            }
        }
        
        return $best_match;
    }
    
    /**
     * Calcola similarità tra due stringhe (Jaro-Winkler semplificato)
     */
    private function calculate_similarity($str1, $str2) {
        if ($str1 === $str2) {
            return 1.0;
        }
        
        // Levenshtein distance
        $distance = levenshtein($str1, $str2);
        $max_len = max(strlen($str1), strlen($str2));
        
        if ($max_len === 0) {
            return 1.0;
        }
        
        $similarity = 1 - ($distance / $max_len);
        
        // Bonus per prefisso comune (Jaro-Winkler)
        $prefix_len = 0;
        $min_len = min(strlen($str1), strlen($str2));
        for ($i = 0; $i < $min_len && $i < 4; $i++) {
            if ($str1[$i] === $str2[$i]) {
                $prefix_len++;
            } else {
                break;
            }
        }
        
        if ($prefix_len > 0) {
            $similarity = $similarity + (0.1 * $prefix_len * (1 - $similarity));
        }
        
        return max(0, min(1, $similarity));
    }
    
    /**
     * Match keywords intelligente con fuzzy matching
     */
    private function match_keywords_intelligent($text) {
        // Prima prova match esatto
        $exact_match = $this->match_keywords($text);
        if ($exact_match && $exact_match['confidence'] >= 0.8) {
            return $exact_match;
        }
        
        // Poi prova fuzzy matching
        $keyword_dict = $this->get_keyword_dict();
        $best_match = null;
        $best_score = 0;
        
        foreach ($keyword_dict as $category => $keywords) {
            foreach ($keywords as $keyword => $config) {
                $kw = is_array($config) ? $keyword : $config;
                $similarity = $this->calculate_similarity($text, $kw);
                
                if ($similarity >= 0.7 && $similarity > $best_score) {
                    $best_score = $similarity;
                    $best_match = [
                        'category' => $category,
                        'subcategory' => is_array($config) ? ($config['subcategory'] ?? null) : null,
                        'confidence' => $similarity * 0.9, // Leggermente più bassa per fuzzy match
                        'matched_keywords' => [$kw],
                    ];
                }
            }
        }
        
        return $best_match ?: $exact_match;
    }
    
    /**
     * Estrae keywords significative da descrizione
     */
    private function extract_significant_keywords($normalized, $category_id) {
        $words = explode(' ', $normalized);
        $keywords = [];
        
        // Rimuovi stop words e parole troppo corte
        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) > 3 && !in_array($word, $this->get_stop_words_list())) {
                $keywords[] = $word;
            }
        }
        
        // Limita a 10 keywords più significative
        return array_slice(array_unique($keywords), 0, 10);
    }
    
    /**
     * Aggiorna pattern simili
     */
    private function update_similar_patterns($normalized, $category_id) {
        $similar_patterns = CategorizationLearning::find_similar_patterns($normalized, 10);
        
        foreach ($similar_patterns as $pattern) {
            if ($pattern->assigned_category_id == $category_id) {
                // Incrementa confidence per pattern corretti
                $new_confidence = min(floatval($pattern->confidence) + 0.05, 1.0);
                CategorizationLearning::update_confidence(
                    $pattern->normalized_description,
                    $category_id,
                    $new_confidence
                );
            }
        }
    }
    
    /**
     * Conta pattern simili per categoria
     */
    private function count_similar_patterns($normalized, $category_id) {
        $similar_patterns = CategorizationLearning::find_similar_patterns($normalized, 100);
        
        $count = 0;
        foreach ($similar_patterns as $pattern) {
            if ($pattern->assigned_category_id == $category_id) {
                $similarity = $this->calculate_similarity($normalized, $pattern->normalized_description);
                if ($similarity >= 0.8) {
                    $count += intval($pattern->match_count ?? 1);
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Promuove pattern a regola permanente
     */
    private function promote_to_permanent_rule($normalized, $category_id, $is_business) {
        $pattern_stats = CategorizationLearning::get_pattern_stats($category_id);
        $avg_confidence = $pattern_stats['avg_confidence'];
        
        if ($avg_confidence >= 0.9) {
            CategorizationRule::promote_from_learning(
                $normalized,
                $category_id,
                null,
                $is_business ? 'business' : 'personal',
                50 // Priority media
            );
        }
    }
    
    /**
     * Ottieni regole apprese (con cache)
     */
    private function get_learned_rules() {
        if ($this->learned_rules_cache === null) {
            // Carica tutte le regole attive
            // Per ora usa tutte le categorie disponibili
            // TODO: Ottenere category_id effettive dal sistema
            $this->learned_rules_cache = [];
            
            global $wpdb;
            $table = $wpdb->prefix . 'fp_finance_hub_categorization_rules';
            $results = $wpdb->get_results(
                "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY priority DESC, match_count DESC"
            );
            
            if (is_array($results)) {
                $this->learned_rules_cache = $results;
            }
        }
        
        return $this->learned_rules_cache;
    }
    
    /**
     * FASE 4.1: Apprendi da correzione utente
     */
    public function learn_from_correction($transaction_id, $old_category_id, $new_category_id) {
        $transaction = TransactionModel::get($transaction_id);
        
        if (!$transaction || empty($transaction->description)) {
            return false;
        }
        
        // Apprendi nuova categoria
        $is_business = !empty($transaction->is_business);
        $this->learn_from_transaction($transaction, $new_category_id, $is_business);
        
        // Se categoria vecchia era automatica, aggiorna confidence negativamente
        if ($old_category_id && $old_category_id != $new_category_id) {
            $this->update_pattern_confidence_negative($transaction, $old_category_id);
        }
        
        return true;
    }
    
    /**
     * Aggiorna confidence negativamente per pattern errato
     */
    private function update_pattern_confidence_negative($transaction, $wrong_category_id) {
        $normalized = $this->normalize_description($transaction->description);
        
        // Trova pattern simili nella categoria sbagliata
        $similar_patterns = CategorizationLearning::find_similar_patterns($normalized, 10);
        
        foreach ($similar_patterns as $pattern) {
            if ($pattern->assigned_category_id == $wrong_category_id) {
                // Diminuisci confidence per pattern che hanno portato a categoria sbagliata
                $new_confidence = max(0.1, floatval($pattern->confidence) - 0.1);
                CategorizationLearning::update_confidence(
                    $pattern->normalized_description,
                    $wrong_category_id,
                    $new_confidence
                );
            }
        }
    }
    
    /**
     * FASE 3.1: Estrazione Keywords Automatica con TF-IDF
     */
    public function extract_keywords_tfidf($category_id, $limit = 20) {
        // Ottieni tutte le descrizioni per questa categoria
        $learning_records = CategorizationLearning::get_by_category($category_id, 1000);
        
        if (empty($learning_records)) {
            return [];
        }
        
        // Calcola TF-IDF
        $all_documents = [];
        $all_words = [];
        
        foreach ($learning_records as $record) {
            $words = explode(' ', $record->normalized_description ?? '');
            $words = array_filter($words, function($w) {
                return strlen($w) > 3 && !in_array($w, $this->get_stop_words_list());
            });
            
            $all_documents[] = $words;
            $all_words = array_merge($all_words, $words);
        }
        
        $total_documents = count($all_documents);
        $unique_words = array_unique($all_words);
        
        $tfidf_scores = [];
        
        foreach ($unique_words as $word) {
            // Term Frequency (TF) in questa categoria
            $tf = array_count_values($all_words)[$word] ?? 0;
            $tf = $tf / max(count($all_words), 1);
            
            // Document Frequency (DF) - in quante descrizioni appare
            $df = 0;
            foreach ($all_documents as $doc) {
                if (in_array($word, $doc)) {
                    $df++;
                }
            }
            
            // Inverse Document Frequency (IDF)
            $idf = $df > 0 ? log($total_documents / $df) : 0;
            
            // TF-IDF score
            $tfidf = $tf * $idf;
            
            if ($tfidf > 0) {
                $tfidf_scores[$word] = $tfidf;
            }
        }
        
        // Ordina per score
        arsort($tfidf_scores);
        
        // Ritorna top N keywords
        return array_slice(array_keys($tfidf_scores), 0, $limit, true);
    }
    
    /**
     * FASE 3.2: Aggiorna dizionario keywords dinamicamente
     */
    public function update_keyword_dict_dynamically($category_id) {
        $keywords = $this->extract_keywords_tfidf($category_id, 30);
        
        if (empty($keywords)) {
            return [];
        }
        
        // Salva keywords estratte per categoria
        // TODO: Salvare in tabella o cache per uso futuro
        // Per ora usa il dizionario esistente come base
        
        return $keywords;
    }
    
    /**
     * FASE 3.2: Clustering Similarità - Raggruppa transazioni simili
     */
    public function cluster_similar_transactions($category_id = null, $limit = 50) {
        global $wpdb;
        $learning_table = $wpdb->prefix . 'fp_finance_hub_categorization_learning';
        $transactions_table = $wpdb->prefix . 'fp_finance_hub_bank_transactions';
        
        $where = [];
        $values = [];
        
        if ($category_id) {
            $where[] = "l.assigned_category_id = %d";
            $values[] = $category_id;
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT 
                    l.normalized_description,
                    l.assigned_category_id,
                    COUNT(*) as frequency,
                    AVG(l.confidence) as avg_confidence
                FROM {$learning_table} l
                {$where_clause}
                GROUP BY l.normalized_description, l.assigned_category_id
                HAVING frequency >= 2
                ORDER BY frequency DESC, avg_confidence DESC
                LIMIT " . intval($limit);
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        $results = $wpdb->get_results($sql);
        
        if (!is_array($results)) {
            return [];
        }
        
        // Raggruppa per similarità
        $clusters = [];
        
        foreach ($results as $pattern) {
            $assigned = false;
            
            // Cerca cluster simile esistente
            foreach ($clusters as &$cluster) {
                $cluster_pattern = $cluster['representative'];
                $similarity = $this->calculate_similarity(
                    $pattern->normalized_description,
                    $cluster_pattern->normalized_description
                );
                
                if ($similarity >= 0.8 && $pattern->assigned_category_id == $cluster_pattern->assigned_category_id) {
                    // Aggiungi a cluster esistente
                    $cluster['patterns'][] = $pattern;
                    $cluster['size']++;
                    $assigned = true;
                    break;
                }
            }
            
            // Crea nuovo cluster se non assegnato
            if (!$assigned) {
                $clusters[] = [
                    'representative' => $pattern,
                    'patterns' => [$pattern],
                    'size' => 1,
                    'category_id' => $pattern->assigned_category_id,
                    'avg_confidence' => floatval($pattern->avg_confidence),
                ];
            }
        }
        
        return $clusters;
    }
    
    /**
     * Auto-categorizza nuovo movimento basandosi su cluster
     */
    public function auto_categorize_from_cluster($normalized, $clusters) {
        $best_match = null;
        $best_confidence = 0;
        
        foreach ($clusters as $cluster) {
            $similarity = $this->calculate_similarity(
                $normalized,
                $cluster['representative']->normalized_description
            );
            
            if ($similarity >= 0.8) {
                $confidence = $similarity * $cluster['avg_confidence'];
                
                // Bonus per cluster grandi (più rappresentativi)
                if ($cluster['size'] >= 5) {
                    $confidence = min($confidence * 1.1, 1.0);
                }
                
                if ($confidence > $best_confidence) {
                    $best_confidence = $confidence;
                    $best_match = [
                        'category' => $cluster['category_id'],
                        'confidence' => $confidence,
                        'method' => 'cluster_match',
                        'cluster_size' => $cluster['size'],
                    ];
                }
            }
        }
        
        return $best_match;
    }
    
    /**
     * Ottieni lista stop words
     */
    private function get_stop_words_list() {
        return [
            'pagamento', 'bonifico', 'ricevuto', 'effettuato',
            's.p.a.', 'srl', 'spa', 's.r.l.',
            'bolletta', 'n.', 'nr', 'numero',
            'via', 'viale', 'piazza', 'corso',
            'il', 'la', 'lo', 'gli', 'le', 'di', 'a', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra',
        ];
    }
    
    /**
     * Dizionario keyword per categoria
     */
    private function get_keyword_dict() {
        return [
            // Alimentari
            'alimentari' => [
                'supermercato' => null,
                'coop' => null,
                'conad' => null,
                'esselunga' => null,
                'carrefour' => null,
                'ipercoop' => null,
                'iper' => null,
                'ipermercato' => null,
                'alimentari' => null,
                'grocery' => null,
                'eurospin' => null,
                'lidl' => null,
                'aldi' => null,
                'pam' => null,
                'despar' => null,
            ],
            
            // Utenze
            'utenze' => [
                'enel' => ['subcategory' => 'luce'],
                'a2a' => ['subcategory' => 'energia'],
                'her' => ['subcategory' => 'energia'],
                'acea' => ['subcategory' => 'energia'],
                'luce' => ['subcategory' => 'luce'],
                'gas' => ['subcategory' => 'gas'],
                'acqua' => ['subcategory' => 'acqua'],
                'energia' => ['subcategory' => 'energia'],
                'bolletta' => null,
                'elettricita' => ['subcategory' => 'luce'],
                'telecom' => null,
                'tim' => ['subcategory' => 'telefono'],
                'vodafone' => ['subcategory' => 'telefono'],
                'wind' => ['subcategory' => 'telefono'],
                'fastweb' => ['subcategory' => 'internet'],
                'italiaonline' => ['subcategory' => 'internet'],
            ],
            
            // Trasporti
            'trasporti' => [
                'benzina' => ['subcategory' => 'carburante'],
                'carburante' => ['subcategory' => 'carburante'],
                'enel x recharge' => ['subcategory' => 'carburante'],
                'rivoltella' => ['subcategory' => 'carburante'],
                'pompa' => ['subcategory' => 'carburante'],
                'stazione servizio' => ['subcategory' => 'carburante'],
                'atm' => ['subcategory' => 'trasporti_urbani'],
                'autostrade' => ['subcategory' => 'pedaggio'],
                'telepass' => ['subcategory' => 'pedaggio'],
                'autogrill' => null,
                'trenitalia' => ['subcategory' => 'treno'],
                'atm milano' => ['subcategory' => 'trasporti_urbani'],
            ],
            
            // Salute
            'salute' => [
                'farmacia' => null,
                'ospedale' => null,
                'medico' => null,
                'dentista' => null,
                'analisi' => null,
                'studi medici' => null,
                'poliambulatorio' => null,
                'clinica' => null,
                'asl' => null,
            ],
            
            // Shopping
            'shopping' => [
                'amazon' => null,
                'ebay' => null,
                'zalando' => null,
                'yoox' => null,
                'asos' => null,
                'decathlon' => null,
            ],
            
            // Business
            'business' => [
                'fattura' => null,
                'invoice' => null,
                'cliente' => null,
                'fornitore' => null,
                'pagamento cliente' => null,
                'ricavi' => null,
                'fatturazione' => null,
            ],
            
            // Svago
            'svago' => [
                'cinema' => null,
                'teatro' => null,
                'ristorante' => null,
                'pizzeria' => null,
                'bar' => null,
                'pub' => null,
                'disco' => null,
                'concerto' => null,
            ],
            
            // Casa
            'casa' => [
                'ikea' => null,
                'leroy merlin' => null,
                'brico' => null,
                'bauhaus' => null,
                'arredamento' => null,
                'ferramenta' => null,
                'bricolage' => null,
            ],
        ];
    }
}
