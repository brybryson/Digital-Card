const apiUrl = 'api/proxy.php?bid=fae5e854d6fae5ee42911677c739ee17344861677c739202&fbclid=IwY2xjawOLj2JleHRuA2FlbQIxMQBzcnRjBmFwcF9pZAEwAAEeMg30c_3Pc2OHsTHGSDy5h3dQ567FjRevKBTZZ_CgnVUBnTqOihb1hvTKbc8_aem_9nZu24Dz4t9Eedwmp_8saA';

let videos = [];
const container = document.getElementById('videoContainer');

function render() {
    container.innerHTML = videos.map(v => `
        <div class="text-center">
            <h4 style="font-family: 'Roboto', sans-serif; font-weight: 600; font-size: 18px; margin-bottom: 10px;">${v.title}</h4>
            <div class="aspect-video bg-gray-200 rounded-lg overflow-hidden mx-auto max-w-sm">
                <iframe width="100%" height="100%" src="https://www.youtube.com/embed/${v.embed}" frameborder="0" allowfullscreen></iframe>
            </div>
            <p class="text-gray-600 text-sm leading-relaxed text-center mt-2">${v.desc}</p>
        </div>
    `).join('');
}

let apiData;

fetch(apiUrl)
.then(response => response.json())
.then(data => {
    apiData = data;
    // Update photo
    const photoEl = document.querySelector('[data-api="photo"]');
    if (photoEl) photoEl.src = data.photo_path;

    // Update name overlay
    const nameText = data.personal_details[0].firstname + '<br>' + data.personal_details[0].lastname;
    const nameOverlay = document.querySelector('.name-text');
    if (nameOverlay) nameOverlay.innerHTML = nameText;

    // Update company - position
    const companyPos = data.personal_details[0].company.trim() + ' - ' + data.personal_details[0].position;
    const companyPosEl = document.querySelector('.company-position-text');
    if (companyPosEl) companyPosEl.textContent = companyPos;


    // Update videos
    videos = data.agent_video.map(v => {
        const embedMatch = v.embed.match(/embed\/([^?]+)/);
        const embedId = embedMatch ? embedMatch[1] : '';
        return {
            title: v.title_content,
            desc: v.description,
            embed: embedId
        };
    });


    // Update address
    const addressEl = document.querySelector('[data-api="address"]');
    if (addressEl) addressEl.textContent = data.personal_details[0].address;

    // Update mobiles
    const mobileEl = document.querySelector('[data-api="mobile"]');
    if (mobileEl) {
        mobileEl.href = 'tel:' + data.personal_details[0].mobile;
        mobileEl.textContent = data.personal_details[0].mobile;
    }
    const mobile1El = document.querySelector('[data-api="mobile1"]');
    if (mobile1El) {
        mobile1El.href = 'tel:' + data.personal_details[0].mobile1;
        mobile1El.textContent = data.personal_details[0].mobile1;
    }

    // Update email
    const emailEl = document.querySelector('[data-api="email"]');
    if (emailEl) {
        emailEl.href = 'mailto:' + data.personal_details[0].email;
        emailEl.textContent = data.personal_details[0].email;
    }

    // Update bio
    const bioTitleEl = document.querySelector('[data-api="bio-title"]');
    if (bioTitleEl) bioTitleEl.textContent = data.agent_bio[0].title;
    const bioDescEl = document.querySelector('[data-api="bio-description"]');
    if (bioDescEl) bioDescEl.textContent = data.agent_bio[0].description;

    // Update social
    const fbEl = document.querySelector('[data-api="social-fb"]');
    if (fbEl) fbEl.href = data.agent_social.find(s => s.sm_code === 'FB').link;
    const igEl = document.querySelector('[data-api="social-ig"]');
    if (igEl) igEl.href = data.agent_social.find(s => s.sm_code === 'IG').link;

    // Update agent link
    const agentLink = document.querySelector('[data-api="agent-link"]');
    if (agentLink) {
        agentLink.href = data.agent_link[0].link;
        // Hardcode title as per design
        const titleEl = agentLink.querySelector('h3');
        if (titleEl) titleEl.textContent = '3AM Digital Media';
    }

    // Update bank accounts
    const bankContainer = document.querySelector('[data-api="bank-accounts"]');
    bankContainer.innerHTML = '';
    data.bank_accounts.forEach((bank) => {
        const bankDiv = document.createElement('div');
        bankDiv.className = 'bg-[#F4F4F4] rounded-[11px] p-6 cursor-pointer hover:bg-gray-200 transition-colors';
        bankDiv.onclick = () => showBankModal(bank);
        const logoSrc = bank.bank_name.toLowerCase() === 'gcash' ? 'images/gcash-logo.png' : 'images/default-bank.png';
        bankDiv.innerHTML = `
            <div class="flex flex-col items-center text-center">
                <img src="${logoSrc}" alt="${bank.bank_name}" class="w-24 h-24 object-contain">
            </div>
        `;
        bankContainer.appendChild(bankDiv);
    });

    // Render videos
    render();

    // Update bio section links
    const phoneLink = document.querySelector('a[data-type="phone"]');
    if (phoneLink) phoneLink.href = 'tel:' + data.personal_details[0].mobile;
    const emailLink = document.querySelector('a[data-type="email"]');
    if (emailLink) emailLink.href = 'mailto:' + data.personal_details[0].email;
    const mobile1Link = document.querySelector('a[data-type="mobile1"]');
    if (mobile1Link) mobile1Link.href = 'tel:' + data.personal_details[0].mobile1;
    const locationLink = document.querySelector('a[data-type="location"]');
    if (locationLink) locationLink.href = 'https://maps.google.com/?q=' + encodeURIComponent(data.personal_details[0].address);
    const smsLink = document.querySelector('a[data-type="sms"]');
    if (smsLink) smsLink.href = 'sms:' + data.personal_details[0].mobile;

})
.catch(error => {
    console.error('Error fetching data:', error);
});


// Toggle API details
const toggleBtn = document.getElementById('toggleApiDetails');
const apiDetails = document.getElementById('apiDetails');
if (toggleBtn && apiDetails) {
    toggleBtn.addEventListener('click', () => {
        apiDetails.classList.toggle('hidden');
        toggleBtn.textContent = apiDetails.classList.contains('hidden') ? 'Show API Details' : 'Hide API Details';
    });
}

// Bank modal
function showBankModal(bank) {
    const modal = document.getElementById('bankModal');
    const modalLogo = document.getElementById('modalLogo');
    const modalHeaderDetails = document.getElementById('modalHeaderDetails');
    const modalContent = document.getElementById('modalContent');

    const logoSrc = bank.bank_name.toLowerCase() === 'gcash' ? 'images/gcash-logo.png' : 'images/default-bank.png';

    modalLogo.src = logoSrc;
    modalHeaderDetails.innerHTML = `
        <p class="font-semibold text-2xl">${bank.bank_name}</p>
    `;

    modalContent.innerHTML = `
        <p class="text-base">Account Number: ${bank.account_no}</p>
        <p class="text-base">Account Name: ${bank.account_type}</p>
    `;

    modal.classList.remove('hidden');
}

const closeModalBtn = document.getElementById('closeModal');
const bankModal = document.getElementById('bankModal');

if (closeModalBtn) {
    closeModalBtn.addEventListener('click', (e) => {
        e.stopPropagation(); // Add this line
        if (bankModal) bankModal.classList.add('hidden');
    });
}

if (bankModal) {
    bankModal.addEventListener('click', (e) => {
        // Only close if clicking the backdrop, not the modal content
        if (e.target === bankModal) {
            bankModal.classList.add('hidden');
        }
    });
// Floating buttons hide/show on scroll
const floatingButtons = document.querySelectorAll('.floating-btn');

function hideButtons() {
    floatingButtons.forEach(btn => {
        btn.classList.add('opacity-0', 'translate-y-4');
    });
}

function showButtons() {
    floatingButtons.forEach(btn => {
        btn.classList.remove('opacity-0', 'translate-y-4');
    });
}

let showTimeout;

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

// Let's Connect button animation
const letsConnectBtn = document.querySelector('.floating-btn img[alt="Let\'s Connect"]').closest('.floating-btn');
const letsConnectSpan = letsConnectBtn.querySelector('span');

letsConnectBtn.addEventListener('mouseenter', () => {
    letsConnectSpan.style.transition = 'opacity 0.3s ease 0.3s';
    letsConnectSpan.style.opacity = '1';
});

letsConnectBtn.addEventListener('mouseleave', () => {
    letsConnectSpan.style.transition = 'opacity 0s ease';
    letsConnectSpan.style.opacity = '0';
});

// Save to Contact button
const saveBtn = document.querySelector('img[alt="Save to Contact"]').closest('a');
saveBtn.addEventListener('click', (e) => {
    e.preventDefault();
    if (!apiData) return;
    const pd = apiData.personal_details[0];
    const fn = pd.firstname + ' ' + pd.lastname;
    const n = pd.lastname + ';' + pd.firstname + ';;;';
    const org = pd.company;
    const adr = pd.address;
    const tel1 = pd.mobile;
    const tel2 = pd.mobile1;
    const email = pd.email;
    const fb = apiData.agent_social.find(s => s.sm_code === 'FB')?.link || '';
    const ig = apiData.agent_social.find(s => s.sm_code === 'IG')?.link || '';
    const vcf = `BEGIN:VCARD
VERSION:3.0
FN:${fn}
N:${n}
ORG:${org}
ADR;TYPE=HOME:${adr}
TEL:${tel1}
TEL;TYPE=HOME:${tel2}
EMAIL:${email}
URL;TYPE=FB:${fb}
URL;TYPE=IG:${ig}
END:VCARD`;
    const blob = new Blob([vcf], {type: 'text/vcard'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = pd.firstname + '_' + pd.lastname + '.vcf';
    a.click();
    URL.revokeObjectURL(url);
});

// Let's Connect modal
letsConnectBtn.addEventListener('click', (e) => {
    e.preventDefault();
    const modal = document.getElementById('connectModal');
    modal.classList.remove('hidden');
});

const connectModal = document.getElementById('connectModal');

if (connectModal) {
    connectModal.addEventListener('click', (e) => {
        if (e.target === connectModal) {
            connectModal.classList.add('hidden');
        }
    });
}

const connectForm = document.getElementById('connectForm');
const connectCancelBtn = document.getElementById('connectCancel');

if (connectForm) {
    connectForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const name = document.getElementById('connectNameInput').value.trim();
        const contact = document.getElementById('connectContactInput').value.trim();
        const email = document.getElementById('connectEmailInput').value.trim();

        // Clear previous errors
        document.getElementById('nameError').classList.add('hidden');
        document.getElementById('contactError').classList.add('hidden');
        document.getElementById('emailError').classList.add('hidden');

        let hasError = false;

        if (!name) {
            document.getElementById('nameError').textContent = 'Name is required.';
            document.getElementById('nameError').classList.remove('hidden');
            hasError = true;
        }

        if (!contact && !email) {
            document.getElementById('contactError').textContent = 'Either Contact or Email is required.';
            document.getElementById('contactError').classList.remove('hidden');
            hasError = true;
        }

        if (contact && !/^09[0-9]{9}$/.test(contact)) {
            document.getElementById('contactError').textContent = 'Contact must start with 09 and be 11 digits.';
            document.getElementById('contactError').classList.remove('hidden');
            hasError = true;
        }

        if (email && (!email.includes('@') || !email.endsWith('.com'))) {
            document.getElementById('emailError').textContent = 'Email must contain @ and end with .com.';
            document.getElementById('emailError').classList.remove('hidden');
            hasError = true;
        }

        if (!hasError) {
            // Valid, close modal
            connectModal.classList.add('hidden');
            // Reset form
            connectForm.reset();
        }
    });
}

if (connectCancelBtn) {
    connectCancelBtn.addEventListener('click', () => {
        connectModal.classList.add('hidden');
        connectForm.reset();
    });
}
}