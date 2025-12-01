# SchoolLink Africa - Quick Start Guide

## 🚀 Getting Started

### 1. Configure Paystack Payment Gateway

**IMPORTANT:** Before testing checkout, you must add your Paystack API keys.

1. Open `config/paystack.php`
2. Replace these lines with your actual keys from https://dashboard.paystack.com/#/settings/developers

```php
// Test mode keys (for development - GET THESE FROM PAYSTACK DASHBOARD)
private const TEST_PUBLIC_KEY = 'pk_test_YOUR_ACTUAL_TEST_PUBLIC_KEY_HERE';
private const TEST_SECRET_KEY = 'sk_test_YOUR_ACTUAL_TEST_SECRET_KEY_HERE';

// Live mode keys (for production - ADD WHEN READY TO GO LIVE)
private const LIVE_PUBLIC_KEY = 'pk_live_YOUR_ACTUAL_LIVE_PUBLIC_KEY_HERE';
private const LIVE_SECRET_KEY = 'sk_live_YOUR_ACTUAL_LIVE_SECRET_KEY_HERE';
```

3. Keep `USE_LIVE_MODE = false` for testing

### 2. Test the Shopping Cart & Checkout

#### A. Login as Student
- Email: (use an existing student account)
- Or register a new student account

#### B. Add Products to Cart
1. Go to "Store" from the navigation menu
2. Click "Add to Cart" on any product
3. Watch the cart badge update in the header
4. You can add from:
   - `store.php` (featured products)
   - `all_products.php` (complete catalog)
   - `single_product.php` (product details)
   - Search results

#### C. View Your Cart
1. Click the cart icon in the header
2. You'll see all items with images and prices
3. Update quantities using +/- buttons
4. Remove items if needed
5. Totals update automatically

#### D. Checkout & Pay
1. Click "Proceed to Checkout" button
2. Fill in:
   - Customer Name (pre-filled from profile)
   - Email Address (pre-filled)
   - Phone Number
   - Delivery Address (optional)
3. Review order summary
4. Click "Place Order and Pay with Paystack"
5. **You'll be redirected to Paystack payment page**

#### E. Complete Test Payment
Use Paystack test card:
- **Card Number:** `5531886652142950`
- **Expiry:** Any future date (e.g., 12/25)
- **CVV:** `564`
- **PIN:** `3310`
- **OTP:** `123456`

#### F. Confirmation
- After successful payment, you'll see the success page
- Order confirmation details displayed
- Click "View My Orders" to see all your orders

### 3. View Order History
- Navigate to `my_orders.php` (link in success page or header)
- See all your orders with:
  - Order number
  - Date placed
  - Total amount
  - Payment status (paid/pending)
  - Order status (processing/shipped/delivered)
- Click "View Details" on any order for full information

### 4. Admin Functions

#### Export Data (Admin Only)
1. Login as admin
2. Go to `admin/settings.php`
3. Click "Export Users" or "Export Schools"
4. CSV file downloads automatically

#### View System Logs (Admin Only)
1. Login as admin
2. Go to `admin/settings.php`
3. Click "View System Logs"
4. New window opens with:
   - Error logs
   - Warning logs
   - Info logs
   - Search and filter functionality

#### Export Reports (Admin Only)
1. Login as admin
2. Go to `admin/reports.php`
3. View various reports
4. Click export button for CSV download

---

## 🧪 Test Card Numbers

### Successful Transactions:
```
Card: 5531886652142950
Expiry: 12/25
CVV: 564
PIN: 3310
OTP: 123456
```

### Failed Transaction (for testing error handling):
```
Card: 5060666666666666666
```

More test cards: https://paystack.com/docs/payments/test-payments

---

## 📁 Important Files

### Configuration
- `config/paystack.php` - Payment gateway settings **← UPDATE THIS FIRST**
- `config/database.php` - Database connection

### Cart System
- `classes/cart_class.php` - Cart logic
- `cart_actions.php` - AJAX API endpoints
- `cart.php` - Cart viewing page

### Checkout & Payment
- `checkout.php` - Checkout form and order creation
- `payment_callback.php` - Handles Paystack response
- `payment_success.php` - Success confirmation page

### Orders
- `my_orders.php` - Order history
- `order_details.php` - Individual order view

### Products
- `store.php` - Featured store page
- `all_products.php` - Complete product catalog
- `single_product.php` - Product details
- `product_search_result.php` - Search results

### Documentation
- `PAYMENT_SETUP.md` - Detailed Paystack setup guide
- `FEATURE_COMPLETION_REPORT.md` - Complete feature list

---

## 🔍 Troubleshooting

### Cart badge not updating?
- Check browser console for JavaScript errors
- Ensure you're logged in as a student
- Hard refresh page (Ctrl+F5)

### Payment not redirecting?
- Verify Paystack API keys are correct in `config/paystack.php`
- Check that keys match test/live mode setting
- Look at browser console for errors

### Orders showing as pending after payment?
- Check `payment_callback.php` processed correctly
- Verify Paystack dashboard shows successful payment
- Check system logs at `admin/system_logs.php`

### "Add to Cart" button not working?
- Ensure JavaScript is enabled
- Check browser console for errors
- Verify you're logged in as a student
- Try hard refresh

---

## ✅ What's Been Fixed

### Bug Fixes Completed:
1. ✅ Session variable mismatches (user_name vs name)
2. ✅ Path resolution issues (relative vs absolute paths)
3. ✅ Undefined array key errors
4. ✅ Placeholder cart functionality → Real API calls
5. ✅ "Coming soon" messages → Actual implementations
6. ✅ Add to cart buttons now functional everywhere

### Features Completed:
1. ✅ Full shopping cart system
2. ✅ Complete Paystack payment integration
3. ✅ Order creation and tracking
4. ✅ Customer order history
5. ✅ Payment verification and callbacks
6. ✅ Admin export functionality
7. ✅ System logs viewer
8. ✅ Real-time cart updates

---

## 🎯 Next Steps

1. **Configure Paystack** (required for checkout to work)
2. **Test the complete flow** from cart to payment
3. **Add your products** through school admin panel
4. **Customize branding** (colors, logo, etc.)
5. **Go live** when ready (update to live Paystack keys)

---

## 📞 Need Help?

- **Paystack Issues**: support@paystack.com
- **Paystack Docs**: https://paystack.com/docs
- **Test Payment Guide**: https://paystack.com/docs/payments/test-payments

---

**Happy Selling! 🎉**

The SchoolLink Africa e-commerce platform is now fully functional and ready for business!
