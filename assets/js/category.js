class CategoryManager {
    constructor() {
        this.categories = [];
        this.currentCategoryId = null;
        this.isEditMode = false;
        
        // DOM elements
        this.categoryForm = document.getElementById('categoryForm');
        this.categoryModal = document.getElementById('categoryModal');
        this.deleteModal = document.getElementById('deleteModal');
        this.loadingSpinner = document.getElementById('loadingSpinner');
        this.categoriesContainer = document.getElementById('categoriesContainer');
        this.emptyState = document.getElementById('emptyState');
        this.alertContainer = document.getElementById('alertContainer');
        
        // Form elements
        this.categoryNameInput = document.getElementById('categoryName');
        
        // Modal elements
        this.modalTitle = document.getElementById('modalTitle');
        this.submitBtn = document.getElementById('submitBtn');
        this.submitText = document.getElementById('submitText');
        this.confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        
        this.bsCategoryModal = new bootstrap.Modal(this.categoryModal);
        this.bsDeleteModal = new bootstrap.Modal(this.deleteModal);
    }
    
    init() {
        this.bindEvents();
        this.loadCategories();
    }
    
    bindEvents() {
        // Form submission
        this.categoryForm.addEventListener('submit', (e) => this.handleFormSubmit(e));
        
        // Delete confirmation
        this.confirmDeleteBtn.addEventListener('click', () => this.deleteCategory());
        
        // Modal events
        this.categoryModal.addEventListener('hidden.bs.modal', () => this.resetForm());
        
        // Real-time validation
        this.categoryNameInput.addEventListener('input', () => this.validateField(this.categoryNameInput));
    }
    
    async loadCategories() {
        try {
            this.showLoading(true);
            
            const response = await fetch('category_controller.php?action=fetch');
            const result = await response.json();
            
            if (result.success) {
                this.categories = result.data;
                this.renderCategories();
            } else {
                this.showAlert('Error loading categories: ' + result.message, 'danger');
            }
        } catch (error) {
            console.error('Error loading categories:', error);
            this.showAlert('Network error occurred while loading categories', 'danger');
        } finally {
            this.showLoading(false);
        }
    }
    
    renderCategories() {
        if (this.categories.length === 0) {
            this.categoriesContainer.style.display = 'none';
            this.emptyState.style.display = 'block';
            return;
        }
        
        this.categoriesContainer.style.display = 'flex';
        this.emptyState.style.display = 'none';
        
        const categoriesHtml = this.categories.map(category => this.createCategoryCard(category)).join('');
        this.categoriesContainer.innerHTML = categoriesHtml;
    }
    
    createCategoryCard(category) {
        const iconClass = this.getCategoryIcon(category.category_name);
        
        return `
            <div class="col-lg-4 col-md-6 col-sm-12">
                <div class="card category-card h-100">
                    <div class="card-body text-center">
                        <div class="category-icon">
                            <i class="bi bi-${iconClass}"></i>
                        </div>
                        
                        <h5 class="card-title fw-bold mb-3">${this.escapeHtml(category.category_name)}</h5>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <span class="badge stats-badge w-100 p-2">
                                    <i class="bi bi-tags"></i><br>
                                    <strong>${category.brand_count}</strong><br>
                                    <small>Brands</small>
                                </span>
                            </div>
                            <div class="col-6">
                                <span class="badge stats-badge w-100 p-2">
                                    <i class="bi bi-box"></i><br>
                                    <strong>${category.product_count}</strong><br>
                                    <small>Products</small>
                                </span>
                            </div>
                        </div>
                        
                        <div class="btn-group w-100" role="group">
                            <button class="btn btn-outline-primary btn-sm" onclick="categoryManager.editCategory(${category.id})" 
                                    title="Edit Category">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <button class="btn btn-outline-danger btn-sm" onclick="categoryManager.confirmDelete(${category.id})" 
                                    title="Delete Category" ${category.brand_count > 0 ? 'disabled' : ''}>
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-transparent">
                        <small class="text-secondary fw-medium">
                            <i class="bi bi-calendar"></i> 
                            Created: ${this.formatDate(category.created_at)}
                        </small>
                    </div>
                </div>
            </div>
        `;
    }
    
    getCategoryIcon(categoryName) {
        const name = categoryName.toLowerCase();
        
        if (name.includes('academic') || name.includes('course')) return 'book';
        if (name.includes('technology') || name.includes('it') || name.includes('computer')) return 'laptop';
        if (name.includes('language')) return 'translate';
        if (name.includes('professional') || name.includes('business')) return 'briefcase';
        if (name.includes('vocational') || name.includes('skill')) return 'tools';
        if (name.includes('art') || name.includes('design')) return 'palette';
        if (name.includes('science')) return 'flask';
        if (name.includes('health') || name.includes('medical')) return 'heart-pulse';
        if (name.includes('music')) return 'music-note';
        if (name.includes('sport') || name.includes('fitness')) return 'trophy';
        
        return 'grid-3x3-gap'; // Default icon
    }
    
    async handleFormSubmit(e) {
        e.preventDefault();
        
        if (!this.validateForm()) {
            return;
        }
        
        try {
            this.setSubmitButton(true);
            
            const formData = new FormData(this.categoryForm);
            const action = this.isEditMode ? 'update' : 'add';
            
            const response = await fetch(`category_controller.php?action=${action}`, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert(result.message, 'success');
                this.bsCategoryModal.hide();
                this.loadCategories(); // Reload categories
            } else {
                this.showAlert(result.message, 'danger');
            }
        } catch (error) {
            console.error('Error submitting form:', error);
            this.showAlert('Network error occurred', 'danger');
        } finally {
            this.setSubmitButton(false);
        }
    }
    
    validateForm() {
        let isValid = true;
        
        // Validate category name
        if (!this.categoryNameInput.value.trim()) {
            this.setFieldError(this.categoryNameInput, 'Category name is required');
            isValid = false;
        } else if (this.categoryNameInput.value.length > 255) {
            this.setFieldError(this.categoryNameInput, 'Category name must be less than 255 characters');
            isValid = false;
        } else {
            this.clearFieldError(this.categoryNameInput);
        }
        
        return isValid;
    }
    
    validateField(field) {
        if (field === this.categoryNameInput) {
            if (!field.value.trim()) {
                return false;
            }
            if (field.value.length > 255) {
                return false;
            }
        }
        
        this.clearFieldError(field);
        return true;
    }
    
    setFieldError(field, message) {
        field.classList.add('is-invalid');
        const feedback = field.parentNode.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.textContent = message;
        }
    }
    
    clearFieldError(field) {
        field.classList.remove('is-invalid');
    }
    
    async editCategory(categoryId) {
        const category = this.categories.find(c => c.id == categoryId);
        if (!category) return;
        
        this.isEditMode = true;
        this.currentCategoryId = categoryId;
        
        // Update modal
        this.modalTitle.innerHTML = '<i class="bi bi-pencil"></i> Edit Category';
        this.submitText.textContent = 'Update Category';
        
        // Populate form
        document.getElementById('categoryId').value = category.id;
        this.categoryNameInput.value = category.category_name;
        
        this.bsCategoryModal.show();
    }
    
    confirmDelete(categoryId) {
        this.currentCategoryId = categoryId;
        this.bsDeleteModal.show();
    }
    
    async deleteCategory() {
        try {
            const formData = new FormData();
            formData.append('category_id', this.currentCategoryId);
            
            const response = await fetch('category_controller.php?action=delete', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert(result.message, 'success');
                this.bsDeleteModal.hide();
                this.loadCategories();
            } else {
                this.showAlert(result.message, 'danger');
            }
        } catch (error) {
            console.error('Error deleting category:', error);
            this.showAlert('Network error occurred', 'danger');
        }
    }
    
    resetForm() {
        this.categoryForm.reset();
        this.isEditMode = false;
        this.currentCategoryId = null;
        
        // Reset modal
        this.modalTitle.innerHTML = '<i class="bi bi-plus-circle"></i> Add New Category';
        this.submitText.textContent = 'Add Category';
        
        // Clear validation
        this.categoryForm.querySelectorAll('.is-invalid').forEach(field => {
            field.classList.remove('is-invalid');
        });
    }
    
    // Utility methods
    showLoading(show) {
        this.loadingSpinner.style.display = show ? 'block' : 'none';
    }
    
    setSubmitButton(loading) {
        if (loading) {
            this.submitBtn.disabled = true;
            this.submitText.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
        } else {
            this.submitBtn.disabled = false;
            this.submitText.textContent = this.isEditMode ? 'Update Category' : 'Add Category';
        }
    }
    
    showAlert(message, type = 'info') {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'}"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        this.alertContainer.innerHTML = alertHtml;
        
        // Auto-dismiss success alerts
        if (type === 'success') {
            setTimeout(() => {
                const alert = this.alertContainer.querySelector('.alert');
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 3000);
        }
    }
    
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
    }
    
    formatDate(dateString) {
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateString).toLocaleDateString(undefined, options);
    }
}