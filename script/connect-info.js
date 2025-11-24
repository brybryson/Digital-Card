const apiUrl = 'api/proxy.php?bid=fae5e854d6fae5ee42911677c739ee17344861677c739202&fbclid=IwY2xjawOLj2JleHRuA2FlbQIxMQBzcnRjBmFwcF9pZAEwAAEeMg30c_3Pc2OHsTHGSDy5h3dQ567FjRevKBTZZ_CgnVUBnTqOihb1hvTKbc8_aem_9nZu24Dz4t9Eedwmp_8saA';

let apiData;

fetch(apiUrl)
.then(response => response.json())
.then(data => {
    apiData = data;
    // Update photo
    const photoEl = document.querySelector('[data-api="photo"]');
    if (photoEl) {
        photoEl.src = data.photo_path;
        photoEl.onload = () => {
            document.getElementById('photoSkeleton').style.display = 'none';
        };
    }
})
.catch(error => {
    console.error('Error fetching data:', error);
});

const connectForm = document.getElementById('connectForm');
const connectCancelBtn = document.getElementById('connectCancel');

if (connectForm) {
    connectForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const firstName = document.getElementById('connectFirstNameInput').value.trim();
        const lastName = document.getElementById('connectLastNameInput').value.trim();
        const contact = document.getElementById('connectContactInput').value.trim();
        const email = document.getElementById('connectEmailInput').value.trim();

        // Clear previous errors
        document.getElementById('nameError').classList.add('hidden');
        document.getElementById('contactError').classList.add('hidden');
        document.getElementById('emailError').classList.add('hidden');

        let hasError = false;

        if (!firstName) {
            document.getElementById('nameError').textContent = 'First name is required.';
            document.getElementById('nameError').classList.remove('hidden');
            hasError = true;
        }

        if (!lastName) {
            document.getElementById('nameError').textContent = 'Last name is required.';
            document.getElementById('nameError').classList.remove('hidden');
            hasError = true;
        }

        if (!contact) {
            document.getElementById('contactError').textContent = 'Contact is required.';
            document.getElementById('contactError').classList.remove('hidden');
            hasError = true;
        } else if (!/^09[0-9]{9}$/.test(contact)) {
            document.getElementById('contactError').textContent = 'Contact must start with 09 and be 11 digits.';
            document.getElementById('contactError').classList.remove('hidden');
            hasError = true;
        }

        if (!email) {
            document.getElementById('emailError').textContent = 'Email is required.';
            document.getElementById('emailError').classList.remove('hidden');
            hasError = true;
        } else if (!email.includes('@') || !email.endsWith('.com')) {
            document.getElementById('emailError').textContent = 'Email must contain @ and end with .com.';
            document.getElementById('emailError').classList.remove('hidden');
            hasError = true;
        }

        if (!hasError) {
            // Valid, show success modal
            const modal = document.getElementById('successModal');
            modal.classList.remove('hidden');
        }
    });
}

if (connectCancelBtn) {
    connectCancelBtn.addEventListener('click', () => {
        window.location.href = 'design-2.html';
        connectForm.reset();
    });
}

const successCloseBtn = document.getElementById('successClose');

if (successCloseBtn) {
    successCloseBtn.addEventListener('click', () => {
        window.location.href = 'design-2.html';
    });
}

// Restrict contact input to numbers and start with 09
const contactInput = document.getElementById('connectContactInput');
if (contactInput) {
    contactInput.addEventListener('input', (e) => {
        let value = e.target.value.replace(/[^0-9]/g, '');
        if (!value.startsWith('09')) {
            value = '09' + value.replace(/^09/, '');
        }
        e.target.value = value;
    });
}

// Save to Contact button (active button for hover)
const saveBtn = document.querySelector('img[alt="Let\'s Connect"]').closest('a');

// Floating buttons hide/show on scroll
const floatingButtons = document.getElementById('floatingButtons');

function hideButtons() {
    floatingButtons.classList.add('opacity-0', 'translate-y-4');
}

function showButtons() {
    floatingButtons.classList.remove('opacity-0', 'translate-y-4');
}

let showTimeout;

// Initially hide and show after 2 seconds if no scroll
hideButtons();
showTimeout = setTimeout(showButtons, 2000);

window.addEventListener('scroll', () => {
    hideButtons();
    clearTimeout(showTimeout);
    showTimeout = setTimeout(showButtons, 2000);
});

window.addEventListener('touchstart', () => {
    hideButtons();
    clearTimeout(showTimeout);
    showTimeout = setTimeout(showButtons, 2000);
});

// Floating buttons hover transfer
const floatingContainer = document.getElementById('floatingButtons');
const buttons = floatingContainer.querySelectorAll('a');
buttons.forEach(btn => {
    if (btn !== saveBtn) {
        btn.addEventListener('mouseenter', () => {
            // Transfer background
            saveBtn.classList.remove('bg-[#363636]', 'rounded-[40px]');
            btn.classList.add('bg-[#363636]', 'rounded-[40px]');
            // Change brightness
            const saveImg = saveBtn.querySelector('img');
            const btnImg = btn.querySelector('img');
            saveImg.style.filter = 'brightness(1)';
            btnImg.style.filter = 'brightness(2)';
        });
        btn.addEventListener('mouseleave', () => {
            // Transfer background back
            btn.classList.remove('bg-[#363636]', 'rounded-[40px]');
            saveBtn.classList.add('bg-[#363636]', 'rounded-[40px]');
            // Change brightness back
            const saveImg = saveBtn.querySelector('img');
            const btnImg = btn.querySelector('img');
            btnImg.style.filter = 'brightness(1)';
            saveImg.style.filter = 'brightness(2)';
        });
    }
});