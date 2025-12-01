/**
 * Brand Management JavaScript
 * Handles AJAX operations for brand CRUD functionality
 */

class BrandManager {
    constructor() {
        this.currentBrandId = null;
        this.init();
    }

    init() {
        this.loadBrands();
        this.bindEvents();
    }

    bindEvents() {
        // Form submission
        document.getElementById('brandForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleFormSubmit();
        });

        // Reset modal when hidden
        document.getElementById('brandModal').addEventListener('hidden.bs.modal', () => {
            this.resetForm();
        });

        // Delete confirmation
        document.getElementById('confirmDeleteBtn').addEventListener('click', () => {
            this.deleteBrand();
        });
    }

    /**
     * Load all brands and display them grouped by category
     */
    async loadBrands() {
        try {
            const response = await fetch('brand_controller.php?action=fetch_brands', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            const data = await response.json();
            
            if (data.success) {
                this.displayBrands(data.brands);
            } else {
                this.showAlert('danger', data.message || 'Failed to load brands');
            }
        } catch (error) {
            console.error('Error loading brands:', error);
            this.showAlert('danger', 'Network error occurred while loading brands');
        }
    }

    /**
     * Display brands grouped by category
     */
    displayBrands(brandsByCategory) {
        const container = document.getElementById('brandsContainer');
        
        if (Object.keys(brandsByCategory).length === 0) {
            container.innerHTML = `
                <div class="text-center py-5">
                    <i class="bi bi-award" style="font-size: 3rem; color: #6c757d;"></i>
                    <h5 class="mt-3 text-muted">No Brands Found</h5>
                    <p class="text-muted">Start by adding your first brand.</p>
                </div>
            `;
            return;
        }

        let html = '';
        
        for (const [categoryName, brands] of Object.entries(brandsByCategory)) {
            html += `
                <div class="category-section mb-4">
                    <h6 class="category-header text-primary mb-3">
                        <i class="bi bi-tags"></i> ${this.escapeHtml(categoryName)}
                        <span class="badge bg-primary rounded-pill ms-2">${brands.length}</span>
                    </h6>
                    <div class="row">
            `;
            
            brands.forEach(brand => {
                html += this.createBrandCard(brand);
            });
            
            html += `
                    </div>
                </div>
            `;
        }
        
        container.innerHTML = html;
        this.bindBrandActions();
    }

    /**
     * Create individual brand card HTML
     */
    createBrandCard(brand) {
        const createdDate = new Date(brand.created_at).toLocaleDateString();
        
        return `
            <div class="col-md-6 col-lg-4 col-xl-3 mb-3">
                <div class="card h-100 brand-card">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-award text-warning"></i>
                                ${this.escapeHtml(brand.brand_name)}
                            </h6>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary" type="button" 
                                        data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <a class="dropdown-item edit-brand" href="#" 
                                           data-brand-id="${brand.id}">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item text-danger delete-brand" href="#" 
                                           data-brand-id="${brand.id}" data-brand-name="${this.escapeHtml(brand.brand_name)}">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <p class="text-muted small mb-2">
                            <i class="bi bi-tag"></i> ${this.escapeHtml(brand.category_name)}
                        </p>
                        <div class="mt-auto">
                            <small class="text-secondary fw-medium">
                                <i class="bi bi-calendar3"></i> Created: ${createdDate}
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Bind click events to brand action buttons
     */
    bindBrandActions() {
        // Edit brand buttons
        document.querySelectorAll('.edit-brand').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const brandId = btn.getAttribute('data-brand-id');
                this.editBrand(brandId);
            });
        });

        // Delete brand buttons
        document.querySelectorAll('.delete-brand').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const brandId = btn.getAttribute('data-brand-id');
                const brandName = btn.getAttribute('data-brand-name');
                this.showDeleteConfirmation(brandId, brandName);
            });
        });
    }

    /**
     * Handle form submission (add or update)
     */
    async handleFormSubmit() {
        const formData = new FormData(document.getElementById('brandForm'));
        const isEdit = this.currentBrandId !== null;
        
        // Add action
        formData.append('action', isEdit ? 'update_brand' : 'add_brand');
        
        // Show loading state
        this.setSubmitLoading(true);

        try {
            const response = await fetch('brand_controller.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                this.showAlert('success', data.message);
                this.hideModal();
                this.loadBrands(); // Reload brands
            } else {
                this.showAlert('danger', data.message);
            }
        } catch (error) {
            console.error('Error submitting form:', error);
            this.showAlert('danger', 'Network error occurred');
        } finally {
            this.setSubmitLoading(false);
        }
    }

    /**
     * Edit brand - load data into form
     */
    async editBrand(brandId) {
        try {
            const formData = new FormData();
            formData.append('action', 'get_brand');
            formData.append('brand_id', brandId);

            const response = await fetch('brand_controller.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                this.currentBrandId = brandId;
                
                // Populate form
                document.getElementById('brandId').value = brandId;
                document.getElementById('brandName').value = data.brand.brand_name;
                document.getElementById('categoryId').value = data.brand.category_id;
                
                // Update modal title
                document.getElementById('brandModalLabel').textContent = 'Edit Brand';
                document.getElementById('submitText').textContent = 'Update Brand';
                
                // Show modal
                new bootstrap.Modal(document.getElementById('brandModal')).show();
            } else {
                this.showAlert('danger', data.message);
            }
        } catch (error) {
            console.error('Error loading brand:', error);
            this.showAlert('danger', 'Failed to load brand data');
        }
    }

    /**
     * Show delete confirmation modal
     */
    showDeleteConfirmation(brandId, brandName) {
        this.currentBrandId = brandId;
        document.getElementById('deleteBrandName').textContent = brandName;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    /**
     * Delete brand
     */
    async deleteBrand() {
        if (!this.currentBrandId) return;

        this.setDeleteLoading(true);

        try {
            const formData = new FormData();
            formData.append('action', 'delete_brand');
            formData.append('brand_id', this.currentBrandId);

            const response = await fetch('brand_controller.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            
            if (data.success) {
                this.showAlert('success', data.message);
                this.hideDeleteModal();
                this.loadBrands(); // Reload brands
            } else {
                this.showAlert('danger', data.message);
            }
        } catch (error) {
            console.error('Error deleting brand:', error);
            this.showAlert('danger', 'Network error occurred');
        } finally {
            this.setDeleteLoading(false);
        }
    }

    /**
     * Reset form to add mode
     */
    resetForm() {
        this.currentBrandId = null;
        document.getElementById('brandForm').reset();
        document.getElementById('brandId').value = '';
        document.getElementById('brandModalLabel').textContent = 'Add New Brand';
        document.getElementById('submitText').textContent = 'Add Brand';
        
        // Clear validation states
        document.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
    }

    /**
     * Show/hide submit loading state
     */
    setSubmitLoading(loading) {
        const btn = document.getElementById('submitBtn');
        const text = document.getElementById('submitText');
        const spinner = document.getElementById('submitSpinner');
        
        btn.disabled = loading;
        text.classList.toggle('d-none', loading);
        spinner.classList.toggle('d-none', !loading);
    }

    /**
     * Show/hide delete loading state
     */
    setDeleteLoading(loading) {
        const btn = document.getElementById('confirmDeleteBtn');
        const text = document.getElementById('deleteText');
        const spinner = document.getElementById('deleteSpinner');
        
        btn.disabled = loading;
        text.classList.toggle('d-none', loading);
        spinner.classList.toggle('d-none', !loading);
    }

    /**
     * Hide modal
     */
    hideModal() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('brandModal'));
        if (modal) {
            modal.hide();
        }
    }

    /**
     * Hide delete modal
     */
    hideDeleteModal() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
        if (modal) {
            modal.hide();
        }
    }

    /**
     * Show alert message
     */
    showAlert(type, message) {
        const alertContainer = document.getElementById('alertContainer');
        
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                ${this.escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        
        alertContainer.innerHTML = alertHtml;
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            const alert = alertContainer.querySelector('.alert');
            if (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new BrandManager();
});