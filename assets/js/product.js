class ProductManager {
    constructor() {
        this.products = [];
        this.currentProductId = null;
        this.isEditMode = false;
        
        // DOM elements
        this.productForm = document.getElementById('productForm');
        this.productModal = document.getElementById('productModal');
        this.deleteModal = document.getElementById('deleteModal');
        this.loadingSpinner = document.getElementById('loadingSpinner');
        this.productsContainer = document.getElementById('productsContainer');
        this.emptyState = document.getElementById('emptyState');
        this.alertContainer = document.getElementById('alertContainer');
        
        // Form elements
        this.categorySelect = document.getElementById('categoryId');
        this.brandSelect = document.getElementById('brandId');
        this.titleInput = document.getElementById('productTitle');
        this.priceInput = document.getElementById('productPrice');
        this.descriptionInput = document.getElementById('productDescription');
        this.keywordsInput = document.getElementById('productKeywords');
        this.imageInput = document.getElementById('productImage');
        this.uploadArea = document.getElementById('uploadArea');
        this.imagePreview = document.getElementById('imagePreview');
        
        // Modal elements
        this.modalTitle = document.getElementById('modalTitle');
        this.submitBtn = document.getElementById('submitBtn');
        this.submitText = document.getElementById('submitText');
        this.confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        
        this.bsProductModal = new bootstrap.Modal(this.productModal);
        this.bsDeleteModal = new bootstrap.Modal(this.deleteModal);
    }
    
    init() {
        this.bindEvents();
        this.loadProducts();
    }
    
    bindEvents() {
        // Form submission
        this.productForm.addEventListener('submit', (e) => this.handleFormSubmit(e));
        
        // Category change - load brands
        this.categorySelect.addEventListener('change', (e) => this.loadBrands(e.target.value));
        
        // File upload events
        this.uploadArea.addEventListener('click', () => this.imageInput.click());
        this.uploadArea.addEventListener('dragover', (e) => this.handleDragOver(e));
        this.uploadArea.addEventListener('dragleave', (e) => this.handleDragLeave(e));
        this.uploadArea.addEventListener('drop', (e) => this.handleDrop(e));
        this.imageInput.addEventListener('change', (e) => this.handleFileSelect(e));
        
        // Delete confirmation
        this.confirmDeleteBtn.addEventListener('click', () => this.deleteProduct());
        
        // Modal events
        this.productModal.addEventListener('hidden.bs.modal', () => this.resetForm());
        
        // Real-time validation
        this.titleInput.addEventListener('input', () => this.validateField(this.titleInput));
        this.priceInput.addEventListener('input', () => this.validateField(this.priceInput));
        this.categorySelect.addEventListener('change', () => this.validateField(this.categorySelect));
        this.brandSelect.addEventListener('change', () => this.validateField(this.brandSelect));
    }
    
    async loadProducts() {
        try {
            this.showLoading(true);
            
            const response = await fetch('product_controller.php?action=fetch');
            const result = await response.json();
            
            if (result.success) {
                this.products = result.data;
                this.renderProducts();
            } else {
                this.showAlert('Error loading products: ' + result.message, 'danger');
            }
        } catch (error) {
            console.error('Error loading products:', error);
            this.showAlert('Network error occurred while loading products', 'danger');
        } finally {
            this.showLoading(false);
        }
    }
    
    async loadBrands(categoryId) {
        try {
            this.brandSelect.disabled = true;
            this.brandSelect.innerHTML = '<option value="">Loading brands...</option>';
            
            if (!categoryId) {
                this.brandSelect.innerHTML = '<option value="">Select Brand</option>';
                return;
            }
            
            const response = await fetch(`product_controller.php?action=get_brands&category_id=${categoryId}`);
            const result = await response.json();
            
            if (result.success) {
                this.brandSelect.innerHTML = '<option value="">Select Brand</option>';
                result.data.forEach(brand => {
                    const option = document.createElement('option');
                    option.value = brand.id;
                    option.textContent = brand.brand_name;
                    this.brandSelect.appendChild(option);
                });
                this.brandSelect.disabled = false;
            } else {
                this.brandSelect.innerHTML = '<option value="">Error loading brands</option>';
                this.showAlert('Error loading brands: ' + result.message, 'warning');
            }
        } catch (error) {
            console.error('Error loading brands:', error);
            this.brandSelect.innerHTML = '<option value="">Error loading brands</option>';
        }
    }
    
    renderProducts() {
        if (this.products.length === 0) {
            this.productsContainer.style.display = 'none';
            this.emptyState.style.display = 'block';
            return;
        }
        
        this.productsContainer.style.display = 'flex';
        this.emptyState.style.display = 'none';
        
        const productsHtml = this.products.map(product => this.createProductCard(product)).join('');
        this.productsContainer.innerHTML = productsHtml;
    }
    
    createProductCard(product) {
        const imageUrl = product.image_path 
            ? `uploads/${product.image_path}` 
            : null;
        
        const imageHtml = imageUrl 
            ? `<img src="${imageUrl}" class="product-image" alt="${this.escapeHtml(product.title)}">`
            : `<div class="product-placeholder">
                 <i class="bi bi-box"></i>
               </div>`;
        
        return `
            <div class="col-lg-4 col-md-6 col-sm-12">
                <div class="card product-card h-100">
                    ${imageHtml}
                    <div class="card-body d-flex flex-column">
                        <div class="mb-2">
                            <span class="badge category-badge me-1">${this.escapeHtml(product.category_name || 'No Category')}</span>
                            <span class="badge brand-badge">${this.escapeHtml(product.brand_name || 'No Brand')}</span>
                        </div>
                        
                        <h5 class="card-title fw-bold mb-2">${this.escapeHtml(product.title)}</h5>
                        
                        ${product.description ? `
                            <p class="card-text text-muted small mb-3">
                                ${this.escapeHtml(product.description.substring(0, 100))}${product.description.length > 100 ? '...' : ''}
                            </p>
                        ` : ''}
                        
                        ${product.keywords ? `
                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="bi bi-tags"></i> ${this.escapeHtml(product.keywords)}
                                </small>
                            </div>
                        ` : ''}
                        
                        <div class="mt-auto">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge price-badge fs-6">$${parseFloat(product.price).toFixed(2)}</span>
                                <small class="text-muted">
                                    <i class="bi bi-person"></i> ${this.escapeHtml(product.creator_name || 'Unknown')}
                                </small>
                            </div>
                            
                            <div class="btn-group w-100" role="group">
                                <button class="btn btn-outline-primary btn-sm" onclick="productManager.editProduct(${product.id})" 
                                        title="Edit Product">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm" onclick="productManager.confirmDelete(${product.id})" 
                                        title="Delete Product">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <button class="btn btn-outline-info btn-sm" onclick="productManager.viewProduct(${product.id})" 
                                        title="View Details">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-transparent">
                        <small class="text-secondary fw-medium">
                            <i class="bi bi-calendar"></i> 
                            Created: ${this.formatDate(product.created_at)}
                        </small>
                    </div>
                </div>
            </div>
        `;
    }
    
    async handleFormSubmit(e) {
        e.preventDefault();
        
        if (!this.validateForm()) {
            return;
        }
        
        try {
            this.setSubmitButton(true);
            
            const formData = new FormData(this.productForm);
            const action = this.isEditMode ? 'update' : 'add';
            
            const response = await fetch(`product_controller.php?action=${action}`, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert(result.message, 'success');
                this.bsProductModal.hide();
                this.loadProducts(); // Reload products
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
        
        // Validate required fields
        const requiredFields = [
            { element: this.categorySelect, message: 'Please select a category' },
            { element: this.brandSelect, message: 'Please select a brand' },
            { element: this.titleInput, message: 'Product title is required' },
            { element: this.priceInput, message: 'Price is required' }
        ];
        
        requiredFields.forEach(field => {
            if (!field.element.value.trim()) {
                this.setFieldError(field.element, field.message);
                isValid = false;
            } else {
                this.clearFieldError(field.element);
            }
        });
        
        // Validate price
        if (this.priceInput.value && (isNaN(this.priceInput.value) || parseFloat(this.priceInput.value) < 0)) {
            this.setFieldError(this.priceInput, 'Price must be a valid positive number');
            isValid = false;
        }
        
        // Validate title length
        if (this.titleInput.value && this.titleInput.value.length > 255) {
            this.setFieldError(this.titleInput, 'Title must be less than 255 characters');
            isValid = false;
        }
        
        return isValid;
    }
    
    validateField(field) {
        if (field.hasAttribute('required') && !field.value.trim()) {
            return false;
        }
        
        if (field === this.priceInput && field.value) {
            if (isNaN(field.value) || parseFloat(field.value) < 0) {
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
    
    editProduct(productId) {
        const product = this.products.find(p => p.id == productId);
        if (!product) return;
        
        this.isEditMode = true;
        this.currentProductId = productId;
        
        // Update modal
        this.modalTitle.innerHTML = '<i class="bi bi-pencil"></i> Edit Product';
        this.submitText.textContent = 'Update Product';
        
        // Populate form
        document.getElementById('productId').value = product.id;
        this.categorySelect.value = product.category_id;
        this.titleInput.value = product.title;
        this.priceInput.value = product.price;
        this.descriptionInput.value = product.description || '';
        this.keywordsInput.value = product.keywords || '';
        
        // Load brands for the category and select the current brand
        this.loadBrands(product.category_id).then(() => {
            this.brandSelect.value = product.brand_id;
        });
        
        // Show current image if exists
        if (product.image_path) {
            this.imagePreview.innerHTML = `
                <img src="uploads/${product.image_path}" class="image-preview" alt="Current image">
                <p class="text-muted small mt-1">Current image (select new image to replace)</p>
            `;
        }
        
        this.bsProductModal.show();
    }
    
    confirmDelete(productId) {
        this.currentProductId = productId;
        this.bsDeleteModal.show();
    }
    
    async deleteProduct() {
        try {
            const formData = new FormData();
            formData.append('product_id', this.currentProductId);
            
            const response = await fetch('product_controller.php?action=delete', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert(result.message, 'success');
                this.bsDeleteModal.hide();
                this.loadProducts();
            } else {
                this.showAlert(result.message, 'danger');
            }
        } catch (error) {
            console.error('Error deleting product:', error);
            this.showAlert('Network error occurred', 'danger');
        }
    }
    
    viewProduct(productId) {
        const product = this.products.find(p => p.id == productId);
        if (!product) return;
        
        // You can implement a detailed view modal here
        // For now, we'll show an alert with product details
        alert(`Product: ${product.title}\nCategory: ${product.category_name}\nBrand: ${product.brand_name}\nPrice: $${product.price}\n\nDescription: ${product.description || 'No description'}`);
    }
    
    resetForm() {
        this.productForm.reset();
        this.isEditMode = false;
        this.currentProductId = null;
        
        // Reset modal
        this.modalTitle.innerHTML = '<i class="bi bi-plus-circle"></i> Add New Product';
        this.submitText.textContent = 'Add Product';
        
        // Clear validation
        this.productForm.querySelectorAll('.is-invalid').forEach(field => {
            field.classList.remove('is-invalid');
        });
        
        // Reset brand select
        this.brandSelect.innerHTML = '<option value="">Select Brand</option>';
        this.brandSelect.disabled = true;
        
        // Clear image preview
        this.imagePreview.innerHTML = '';
    }
    
    // File upload handlers
    handleDragOver(e) {
        e.preventDefault();
        this.uploadArea.classList.add('dragover');
    }
    
    handleDragLeave(e) {
        e.preventDefault();
        this.uploadArea.classList.remove('dragover');
    }
    
    handleDrop(e) {
        e.preventDefault();
        this.uploadArea.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            this.imageInput.files = files;
            this.handleFileSelect({ target: { files } });
        }
    }
    
    handleFileSelect(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        // Validate file type
        if (!file.type.match(/^image\/(jpeg|png|gif)$/)) {
            this.showAlert('Please select a valid image file (JPEG, PNG, or GIF)', 'warning');
            this.imageInput.value = '';
            return;
        }
        
        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            this.showAlert('Image file must be less than 5MB', 'warning');
            this.imageInput.value = '';
            return;
        }
        
        // Show preview
        const reader = new FileReader();
        reader.onload = (e) => {
            this.imagePreview.innerHTML = `
                <img src="${e.target.result}" class="image-preview" alt="Preview">
                <p class="text-muted small mt-1">${file.name} (${this.formatFileSize(file.size)})</p>
            `;
        };
        reader.readAsDataURL(file);
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
            this.submitText.textContent = this.isEditMode ? 'Update Product' : 'Add Product';
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
    
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}