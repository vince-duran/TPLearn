<?php
/**
 * TPLearn Standard UI Components Helper
 * Generates consistent UI elements across all dashboards
 */

class TPLearnUI {
    
    /**
     * Generate a standardized search bar with filters
     */
    public static function renderSearchBar($config = []) {
        $defaults = [
            'search_placeholder' => 'Search...',
            'search_value' => '',
            'search_name' => 'search',
            'search_id' => 'searchInput',
            'filters' => [],
            'form_method' => 'GET',
            'form_action' => '',
            'show_buttons' => true,
            'additional_classes' => ''
        ];
        
        $config = array_merge($defaults, $config);
        
        ob_start();
        ?>
        <div class="tplearn-search-container <?= htmlspecialchars($config['additional_classes']) ?>">
            <form method="<?= htmlspecialchars($config['form_method']) ?>" action="<?= htmlspecialchars($config['form_action']) ?>" class="tplearn-search-group">
                
                <!-- Search Input -->
                <div class="tplearn-search-input-container">
                    <svg class="tplearn-search-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                    </svg>
                    <input 
                        type="text" 
                        name="<?= htmlspecialchars($config['search_name']) ?>" 
                        id="<?= htmlspecialchars($config['search_id']) ?>"
                        value="<?= htmlspecialchars($config['search_value']) ?>" 
                        placeholder="<?= htmlspecialchars($config['search_placeholder']) ?>" 
                        class="tplearn-search-input">
                </div>

                <!-- Filters -->
                <?php foreach ($config['filters'] as $filter): ?>
                    <select name="<?= htmlspecialchars($filter['name']) ?>" class="tplearn-filter-select" <?= isset($filter['onchange']) ? 'onchange="' . htmlspecialchars($filter['onchange']) . '"' : '' ?>>
                        <?php foreach ($filter['options'] as $value => $label): ?>
                            <option value="<?= htmlspecialchars($value) ?>" <?= ($filter['selected'] ?? '') === $value ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endforeach; ?>

                <!-- Action Buttons -->
                <?php if ($config['show_buttons']): ?>
                    <div class="tplearn-search-actions">
                        <button type="submit" class="tplearn-btn tplearn-btn-primary tplearn-btn-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            Search
                        </button>
                        <a href="<?= htmlspecialchars($config['form_action']) ?>" class="tplearn-btn tplearn-btn-secondary tplearn-btn-sm">
                            Clear
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate a standardized table structure
     */
    public static function renderTable($config = []) {
        $defaults = [
            'headers' => [],
            'rows' => [],
            'empty_message' => 'No data available',
            'empty_description' => '',
            'sortable' => false,
            'responsive' => true,
            'hover_effects' => true,
            'additional_classes' => ''
        ];
        
        $config = array_merge($defaults, $config);
        
        ob_start();
        ?>
        <div class="tplearn-table-container <?= htmlspecialchars($config['additional_classes']) ?>">
            <div class="tplearn-table-wrapper">
                <table class="tplearn-table">
                    <!-- Table Header -->
                    <thead class="tplearn-table-header">
                        <tr class="tplearn-table-header-row">
                            <?php foreach ($config['headers'] as $header): ?>
                                <th class="tplearn-table-header-cell <?= $config['sortable'] ? 'sortable' : '' ?> <?= isset($header['responsive_class']) ? htmlspecialchars($header['responsive_class']) : '' ?>"
                                    <?= $config['sortable'] && isset($header['sort_key']) ? 'onclick="sortTable(\'' . htmlspecialchars($header['sort_key']) . '\')"' : '' ?>>
                                    <?= htmlspecialchars($header['label']) ?>
                                    <?php if ($config['sortable'] && isset($header['sort_key'])): ?>
                                        <svg class="w-4 h-4 inline-block ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                                        </svg>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    
                    <!-- Table Body -->
                    <tbody class="tplearn-table-body">
                        <?php if (empty($config['rows'])): ?>
                            <tr>
                                <td colspan="<?= count($config['headers']) ?>" class="tplearn-table-cell">
                                    <?= self::renderEmptyState([
                                        'title' => $config['empty_message'],
                                        'description' => $config['empty_description']
                                    ]) ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($config['rows'] as $row): ?>
                                <tr class="tplearn-table-row <?= $config['hover_effects'] ? 'hover:bg-gray-50' : '' ?>">
                                    <?php foreach ($row as $cell): ?>
                                        <td class="tplearn-table-cell <?= isset($cell['responsive_class']) ? htmlspecialchars($cell['responsive_class']) : '' ?>">
                                            <?= $cell['content'] ?? $cell ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate standardized buttons
     */
    public static function renderButton($config = []) {
        $defaults = [
            'text' => 'Button',
            'type' => 'button',
            'variant' => 'primary', // primary, secondary, danger, warning
            'size' => 'normal', // sm, normal, lg
            'icon' => null,
            'href' => null,
            'onclick' => null,
            'disabled' => false,
            'additional_classes' => ''
        ];
        
        $config = array_merge($defaults, $config);
        
        $classes = [
            'tplearn-btn',
            'tplearn-btn-' . $config['variant'],
            $config['size'] !== 'normal' ? 'tplearn-btn-' . $config['size'] : '',
            $config['additional_classes']
        ];
        
        $classString = implode(' ', array_filter($classes));
        
        $attributes = [
            'class' => $classString,
            'type' => $config['type'],
            'onclick' => $config['onclick'],
            'disabled' => $config['disabled'] ? 'disabled' : null
        ];
        
        $attributeString = '';
        foreach ($attributes as $key => $value) {
            if ($value !== null && $value !== false) {
                $attributeString .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
            }
        }
        
        ob_start();
        
        if ($config['href']): ?>
            <a href="<?= htmlspecialchars($config['href']) ?>" <?= $attributeString ?>>
                <?= $config['icon'] ? $config['icon'] : '' ?>
                <?= htmlspecialchars($config['text']) ?>
            </a>
        <?php else: ?>
            <button <?= $attributeString ?>>
                <?= $config['icon'] ? $config['icon'] : '' ?>
                <?= htmlspecialchars($config['text']) ?>
            </button>
        <?php endif;
        
        return ob_get_clean();
    }

    /**
     * Generate standardized cards
     */
    public static function renderCard($config = []) {
        $defaults = [
            'title' => '',
            'description' => '',
            'content' => '',
            'footer' => '',
            'hover_effect' => true,
            'additional_classes' => ''
        ];
        
        $config = array_merge($defaults, $config);
        
        ob_start();
        ?>
        <div class="tplearn-card <?= $config['hover_effect'] ? '' : 'no-hover' ?> <?= htmlspecialchars($config['additional_classes']) ?>">
            <?php if ($config['title'] || $config['description']): ?>
                <div class="tplearn-card-header">
                    <?php if ($config['title']): ?>
                        <h3><?= htmlspecialchars($config['title']) ?></h3>
                    <?php endif; ?>
                    <?php if ($config['description']): ?>
                        <p><?= htmlspecialchars($config['description']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($config['content']): ?>
                <div class="tplearn-card-body">
                    <?= $config['content'] ?>
                </div>
            <?php endif; ?>
            
            <?php if ($config['footer']): ?>
                <div class="tplearn-card-footer">
                    <?= $config['footer'] ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate standardized stat cards
     */
    public static function renderStatCard($config = []) {
        $defaults = [
            'title' => '',
            'value' => '0',
            'icon' => null,
            'icon_color' => 'green',
            'badge' => null,
            'badge_color' => 'green',
            'additional_classes' => ''
        ];
        
        $config = array_merge($defaults, $config);
        
        ob_start();
        ?>
        <div class="tplearn-stat-card <?= htmlspecialchars($config['additional_classes']) ?>">
            <div class="tplearn-stat-header">
                <?php if ($config['icon']): ?>
                    <div class="tplearn-stat-icon <?= htmlspecialchars($config['icon_color']) ?>">
                        <?= $config['icon'] ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($config['badge']): ?>
                    <span class="tplearn-stat-badge <?= htmlspecialchars($config['badge_color']) ?>">
                        <?= htmlspecialchars($config['badge']) ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="tplearn-stat-number"><?= htmlspecialchars($config['value']) ?></div>
            <div class="tplearn-stat-label"><?= htmlspecialchars($config['title']) ?></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate standardized status badges
     */
    public static function renderBadge($config = []) {
        $defaults = [
            'text' => '',
            'variant' => 'neutral', // success, warning, danger, info, neutral
            'icon' => null,
            'additional_classes' => ''
        ];
        
        $config = array_merge($defaults, $config);
        
        ob_start();
        ?>
        <span class="tplearn-badge tplearn-badge-<?= htmlspecialchars($config['variant']) ?> <?= htmlspecialchars($config['additional_classes']) ?>">
            <?= $config['icon'] ? $config['icon'] : '' ?>
            <?= htmlspecialchars($config['text']) ?>
        </span>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate standardized empty states
     */
    public static function renderEmptyState($config = []) {
        $defaults = [
            'icon' => null,
            'title' => 'No data available',
            'description' => '',
            'action_button' => null,
            'additional_classes' => ''
        ];
        
        $config = array_merge($defaults, $config);
        
        ob_start();
        ?>
        <div class="tplearn-empty-state <?= htmlspecialchars($config['additional_classes']) ?>">
            <?php if ($config['icon']): ?>
                <div class="tplearn-empty-icon">
                    <?= $config['icon'] ?>
                </div>
            <?php else: ?>
                <svg class="tplearn-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            <?php endif; ?>
            
            <h3 class="tplearn-empty-title"><?= htmlspecialchars($config['title']) ?></h3>
            
            <?php if ($config['description']): ?>
                <p class="tplearn-empty-description"><?= htmlspecialchars($config['description']) ?></p>
            <?php endif; ?>
            
            <?php if ($config['action_button']): ?>
                <?= $config['action_button'] ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate loading spinner
     */
    public static function renderLoading($config = []) {
        $defaults = [
            'text' => 'Loading...',
            'show_text' => true,
            'additional_classes' => ''
        ];
        
        $config = array_merge($defaults, $config);
        
        ob_start();
        ?>
        <div class="tplearn-loading <?= htmlspecialchars($config['additional_classes']) ?>">
            <div class="tplearn-spinner"></div>
            <?php if ($config['show_text']): ?>
                <span class="ml-2 text-gray-600"><?= htmlspecialchars($config['text']) ?></span>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
?>