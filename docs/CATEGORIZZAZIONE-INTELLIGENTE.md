# 🧠 Categorizzazione Intelligente Movimenti Bancari

## 📋 **OVERVIEW**

Sistema di categorizzazione automatica che analizza le causali/descrizioni dei movimenti bancari per assegnare automaticamente la categoria corretta (aziendale/familiare) e sottocategoria (alimentari, utenze, trasporti, etc.).

---

## 🎯 **OBIETTIVO**

**Analizzare automaticamente** la descrizione/causale del movimento bancario e assegnare:
- ✅ Tipo movimento: **Business** o **Personal/Familiare**
- ✅ Categoria: **Alimentari**, **Utenze**, **Trasporti**, **Salute**, etc.
- ✅ Sottocategoria (opzionale): **Luce**, **Gas**, **Acqua** (sotto Utenze)

---

## 🔧 **TECNICHE IMPLEMENTAZIONE**

### 1. **Pattern Matching con Keywords** ⭐ **PRINCIPALE**

**Come Funziona:**
- Dizionario di keyword per ogni categoria
- Ricerca keyword nella descrizione (case-insensitive)
- Punteggio di matching (più keyword = più sicuro)

**Esempio:**
```php
$categories = [
    'alimentari' => ['supermercato', 'coop', 'conad', 'esselunga', 'carrefour', 'alimentari', 'grocery'],
    'utenze' => ['enel', 'a2a', 'her', 'acea', 'luce', 'gas', 'acqua', 'energia', 'bolletta'],
    'trasporti' => ['benzina', 'carburante', 'enel x recharge', 'rivoltella', 'pompa', 'stazione servizio', 'atm'],
    'salute' => ['farmacia', 'ospedale', 'medico', 'dentista', 'analisi', 'studi medici'],
    // ...
];
```

**Vantaggi:**
- ✅ Veloce e performante
- ✅ Facile da configurare
- ✅ Preciso per pattern comuni
- ✅ Funziona subito

---

### 2. **Analisi del Testo (NLP Base)**

**Come Funziona:**
- Rimozione stop words ("bonifico", "pagamento", etc.)
- Estrazione keyword principali
- Matching semantico migliorato
- Gestione abbreviazioni comuni

**Esempio:**
```php
// Descrizione: "PAGAMENTO BONIFICO ENEL ENERGIA S.P.A. BOLETTA N.12345"
// → Rimuovi: "PAGAMENTO", "BONIFICO", "S.P.A.", "BOLETTA N."
// → Keyword rimaste: "ENEL", "ENERGIA"
// → Categoria: "utenze" (match con "enel", "energia")
```

---

### 3. **Regole Configurabili dall'Utente**

**Come Funziona:**
- Utente può creare regole personalizzate
- Pattern regex personalizzati
- Assegnazione forzata per specifici importi/riferimenti

**Esempio Configurazione:**
```php
// Regola utente: "Se descrizione contiene 'AMAZON' → Categoria: 'Shopping Online'"
// Regola utente: "Se descrizione contiene 'PAYPAL' e importo < 100€ → 'Varie'"
// Regola utente: "Se riferimento contiene 'FAT-' → Categoria: 'Business', match fattura"
```

---

### 4. **Apprendimento Automatico (Machine Learning Semplice)**

**Come Funziona:**
- Memorizza categorizzazioni manuali dell'utente
- Analizza pattern comuni
- Suggerisce categorie simili per nuovi movimenti
- Migliora precisione nel tempo

**Esempio:**
```php
// Utente categorizza manualmente:
// "COOP VIA ROMA" → "Alimentari" (3 volte)
// "COOP MILANO" → "Alimentari" (5 volte)

// Sistema impara:
// Pattern: "COOP" → alta probabilità "Alimentari"
// Applica automaticamente a nuovi: "COOP FIRENZE"
```

---

## 🗄️ **STRUTTURA DATABASE**

### Tabella Regole Categorizzazione

```sql
CREATE TABLE wp_fp_finance_hub_categorization_rules (
  id BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
  rule_type VARCHAR(50) NOT NULL, -- 'keyword', 'regex', 'amount_range', 'reference'
  pattern TEXT NOT NULL, -- pattern da cercare
  category_id BIGINT(20) NOT NULL,
  subcategory_id BIGINT(20) NULL,
  transaction_type VARCHAR(50) DEFAULT 'personal', -- 'business' o 'personal'
  priority INT DEFAULT 0, -- priorità regola (più alta = applicata prima)
  is_active BOOLEAN DEFAULT TRUE,
  match_count INT DEFAULT 0, -- quante volte ha matchato (per ML)
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY category_id (category_id),
  KEY priority (priority)
);
```

### Tabella Apprendimento

```sql
CREATE TABLE wp_fp_finance_hub_categorization_learning (
  id BIGINT(20) AUTO_INCREMENT PRIMARY KEY,
  transaction_id BIGINT(20) NOT NULL,
  original_description TEXT NOT NULL,
  normalized_description TEXT, -- testo normalizzato (lowercase, rimossi stop words)
  assigned_category_id BIGINT(20) NOT NULL,
  assigned_by VARCHAR(50) DEFAULT 'manual', -- 'automatic', 'manual', 'rule', 'ml'
  confidence DECIMAL(3,2) DEFAULT 1.00, -- livello confidenza (0.00-1.00)
  keywords_extracted TEXT, -- JSON array keyword trovate
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY transaction_id (transaction_id),
  KEY category_id (assigned_category_id)
);
```

---

## 🔍 **ALGORITMO CATEGORIZZAZIONE**

### Flusso Completo

```
1. Nuovo movimento importato
   ↓
2. Normalizza descrizione
   - Lowercase
   - Rimuovi caratteri speciali ridondanti
   - Rimuovi stop words comuni
   ↓
3. Applica Regole Utente (Priorità Alta)
   - Regex personalizzate
   - Pattern specifici utente
   ↓
4. Pattern Matching Keywords (Priorità Media)
   - Cerca keyword nel dizionario
   - Calcola punteggio matching
   ↓
5. Machine Learning (Priorità Bassa)
   - Cerca movimenti simili già categorizzati
   - Suggerisci categoria basata su pattern storici
   ↓
6. Selezione Categoria Finale
   - Scegli categoria con punteggio più alto
   - Se confidenza < soglia → marca come "Da rivedere"
   ↓
7. Salva risultato
   - Assegna categoria al movimento
   - Salva per apprendimento futuro
```

---

## 📝 **ESEMPI PRATICI**

### Esempio 1: Utenze

**Movimento:**
```
Descrizione: "PAGAMENTO BONIFICO ENEL ENERGIA S.P.A. BOLETTA N.123456789"
Importo: -89.50€
```

**Analisi:**
1. Normalizza: `"pagamento bonifico enel energia s.p.a. bolletta n.123456789"`
2. Rimuovi stop words: `"enel energia bolletta"`
3. Keyword trovate: `["enel", "energia"]`
4. Match categorie:
   - `utenze`: match "enel" (punteggio: 0.8)
   - `utenze`: match "energia" (punteggio: 0.9)
5. **Risultato:** Categoria `Utenze` (Luce), Tipo `Personal`, Confidenza: 0.9

---

### Esempio 2: Alimentari

**Movimento:**
```
Descrizione: "PAGAMENTO POS COOP VIA ROMA 123 - MILANO"
Importo: -125.30€
```

**Analisi:**
1. Normalizza: `"pagamento pos coop via roma 123 milano"`
2. Rimuovi stop words: `"coop milano"`
3. Keyword trovate: `["coop"]`
4. Match categorie:
   - `alimentari`: match "coop" (punteggio: 0.95)
5. **Risultato:** Categoria `Alimentari`, Tipo `Personal`, Confidenza: 0.95

---

### Esempio 3: Business (Fattura)

**Movimento:**
```
Descrizione: "BONIFICO RICEVUTO CLIENTE ABC SRL - FATTURA N.2025/001"
Importo: +1500.00€
```

**Analisi:**
1. Normalizza: `"bonifico ricevuto cliente abc srl fattura n.2025/001"`
2. Keyword trovate: `["fattura", "cliente", "srl"]`
3. Match categorie:
   - Pattern business: "fattura" + "cliente" → `business` (punteggio: 1.0)
4. **Risultato:** Categoria `Business` (Fatture), Tipo `Business`, Confidenza: 1.0

---

### Esempio 4: Trasporti

**Movimento:**
```
Descrizione: "PAGAMENTO POS ENEL X RECHARGE - STAZIONE SERV."
Importo: -45.00€
```

**Analisi:**
1. Keyword trovate: `["enel x recharge", "stazione serv"]`
2. Match categorie:
   - `trasporti`: match "enel x recharge" (punteggio: 0.9)
   - `trasporti`: match "stazione serv" (punteggio: 0.8)
3. **Risultato:** Categoria `Trasporti` (Carburante), Tipo `Personal`, Confidenza: 0.9

---

## ⚙️ **IMPLEMENTAZIONE CLASSE**

### Classe `CategorizationEngine`

```php
namespace FP\FinanceHub\Services;

class CategorizationEngine {
    
    private $keyword_dict = [];
    private $stop_words = [];
    private $ml_model = null;
    
    /**
     * Categorizza un movimento bancario
     */
    public function categorize($transaction) {
        // 1. Normalizza descrizione
        $normalized = $this->normalize_description($transaction->description);
        
        // 2. Applica regole utente
        $rule_match = $this->apply_user_rules($transaction);
        if ($rule_match && $rule_match['confidence'] > 0.8) {
            return $rule_match;
        }
        
        // 3. Pattern matching keywords
        $keyword_match = $this->match_keywords($normalized);
        
        // 4. Machine learning (se disponibile)
        $ml_match = $this->ml_predict($normalized);
        
        // 5. Combina risultati
        return $this->select_best_match([
            $rule_match,
            $keyword_match,
            $ml_match
        ]);
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
            'via', 'viale', 'piazza', 'corso'
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
        
        foreach ($this->get_keyword_dict() as $category => $keywords) {
            $score = 0;
            $matched_keywords = [];
            
            foreach ($keywords as $keyword) {
                if (stripos($text, $keyword) !== false) {
                    $score += 1;
                    $matched_keywords[] = $keyword;
                }
            }
            
            if ($score > 0) {
                $matches[$category] = [
                    'score' => $score / count($keywords), // Normalizza
                    'matched_keywords' => $matched_keywords
                ];
            }
        }
        
        // Ritorna match con punteggio più alto
        if (!empty($matches)) {
            arsort($matches);
            $best = array_key_first($matches);
            return [
                'category' => $best,
                'confidence' => min($matches[$best]['score'], 1.0),
                'method' => 'keyword',
                'matched_keywords' => $matches[$best]['matched_keywords']
            ];
        }
        
        return null;
    }
    
    /**
     * Dizionario keyword per categoria
     */
    private function get_keyword_dict() {
        return [
            // Alimentari
            'alimentari' => [
                'supermercato', 'coop', 'conad', 'esselunga', 'carrefour',
                'ipercoop', 'iper', 'ipermercato', 'alimentari', 'grocery',
                'eurospin', 'lidl', 'aldi', 'pam', 'despar'
            ],
            
            // Utenze
            'utenze' => [
                'enel', 'a2a', 'her', 'acea', 'luce', 'gas', 'acqua',
                'energia', 'bolletta', 'elettricita', 'telecom',
                'tim', 'vodafone', 'wind', 'fastweb', 'italiaonline'
            ],
            
            // Trasporti
            'trasporti' => [
                'benzina', 'carburante', 'enel x recharge', 'rivoltella',
                'pompa', 'stazione servizio', 'atm', 'autostrade',
                'telepass', 'autogrill', 'trenitalia', 'atm milano'
            ],
            
            // Salute
            'salute' => [
                'farmacia', 'ospedale', 'medico', 'dentista', 'analisi',
                'studi medici', 'poliambulatorio', 'clinica', 'asl'
            ],
            
            // Shopping
            'shopping' => [
                'amazon', 'ebay', 'zalando', 'yoox', 'asos', 'decathlon'
            ],
            
            // Business
            'business' => [
                'fattura', 'invoice', 'cliente', 'fornitore', 'pagamento cliente',
                'ricavi', 'fatturazione'
            ],
            
            // Svago
            'svago' => [
                'cinema', 'teatro', 'ristorante', 'pizzeria', 'bar',
                'pub', 'disco', 'concerto'
            ],
            
            // Casa
            'casa' => [
                'ikea', 'leroy merlin', 'brico', 'bauhaus', 'arredamento',
                'ferramenta', 'bricolage'
            ]
        ];
    }
}
```

---

## 🎯 **FEATURES AVANZATE**

### 1. **Apprendimento Automatico Semplice**

```php
/**
 * Impara da categorizzazioni manuali
 */
public function learn_from_manual($transaction_id, $category_id) {
    // Estrai pattern dalla descrizione
    $description = get_transaction_description($transaction_id);
    $normalized = $this->normalize_description($description);
    $keywords = $this->extract_keywords($normalized);
    
    // Salva nel database per future predizioni
    save_learning_example([
        'keywords' => $keywords,
        'category_id' => $category_id,
        'description' => $normalized
    ]);
}
```

### 2. **Suggerimenti Intelligenti**

```php
/**
 * Suggerisci categoria per movimento non categorizzato
 */
public function suggest_category($description) {
    // Cerca movimenti simili già categorizzati
    $similar = $this->find_similar_transactions($description);
    
    if (!empty($similar)) {
        // Prendi categoria più frequente
        $most_common = $this->get_most_common_category($similar);
        return [
            'category' => $most_common,
            'confidence' => count($similar) / 10, // aumenta con più esempi
            'similar_count' => count($similar)
        ];
    }
    
    return null;
}
```

### 3. **Rilevamento Automatico Business vs Personal**

```php
/**
 * Determina se movimento è business o personal
 */
private function detect_transaction_type($description, $amount) {
    // Pattern business
    $business_keywords = ['fattura', 'cliente', 'fornitore', 'srl', 's.p.a.'];
    
    // Pattern personal
    $personal_keywords = ['supermercato', 'coop', 'farmacia', 'benzina'];
    
    // Conta match
    $business_score = $this->count_matches($description, $business_keywords);
    $personal_score = $this->count_matches($description, $personal_keywords);
    
    // Se importo molto alto (>1000€) e contiene "fattura" → business
    if ($amount > 1000 && stripos($description, 'fattura') !== false) {
        return 'business';
    }
    
    // Altrimenti usa punteggio
    return $business_score > $personal_score ? 'business' : 'personal';
}
```

---

## ✅ **VANTAGGI**

1. **Automatico**: Categorizza movimenti senza intervento utente
2. **Apprendimento**: Migliora nel tempo con categorizzazioni manuali
3. **Configurabile**: Utente può aggiungere regole personalizzate
4. **Veloce**: Pattern matching è molto performante
5. **Preciso**: Alta confidenza per pattern comuni (>90%)

---

## 🔄 **WORKFLOW COMPLETO**

```
1. Import CSV/OFX
   ↓
2. Per ogni movimento:
   - Analizza descrizione
   - Categorizza automaticamente
   - Se confidenza < 0.7 → marca "Da rivedere"
   ↓
3. Utente può:
   - Confermare categoria automatica
   - Correggere categoria errata (apprendimento)
   - Aggiungere regole personalizzate
   ↓
4. Sistema apprende:
   - Salva pattern corretti
   - Migliora precisione futura
```

---

**Questa categorizzazione intelligente renderà il plugin molto più user-friendly!** 🚀
