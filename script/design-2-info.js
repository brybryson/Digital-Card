const apiUrl = 'api/proxy.php?bid=fae5e854d6fae5ee42911677c739ee17344861677c739202&fbclid=IwY2xjawOLj2JleHRuA2FlbQIxMQBzcnRjBmFwcF9pZAEwAAEeMg30c_3Pc2OHsTHGSDy5h3dQ567FjRevKBTZZ_CgnVUBnTqOihb1hvTKbc8_aem_9nZu24Dz4t9Eedwmp_8saA';

let videos = [];
const container = document.getElementById('videoContainer');

function render() {
    if (videos.length <= 1) {
        container.innerHTML = videos.map(v => `
            <div class="text-center">
                <div class="aspect-video bg-gray-200 rounded-[10px] overflow-hidden mx-auto max-w-sm">
                    <iframe width="100%" height="100%" src="https://www.youtube.com/embed/${v.embed}" frameborder="0" allowfullscreen></iframe>
                </div>
                <div class="bg-black text-white font-semibold text-base px-3 py-2 mt-4 rounded-[40px] w-[290px] mx-auto" style="font-family: 'Inter', sans-serif; font-size: 16px; min-height: 32px;">${v.title}</div>
                <p class="text-black text-sm mt-4 px-4" style="font-family: 'Inter', sans-serif; font-size: 14px; line-height: 18px;">${v.desc}</p>
            </div>
        `).join('');
    } else {
        container.innerHTML = `
            <div class="carousel relative h-full overflow-hidden">
                <div class="carousel-inner flex gap-4 transition-transform duration-500 ease-in-out" id="carouselInner">
                    ${videos.map(v => `
                        <div class="carousel-item flex-shrink-0 w-full text-center">
                            <div class="aspect-video bg-gray-200 rounded-[10px] overflow-hidden mx-auto max-w-sm">
                                <iframe width="100%" height="100%" src="https://www.youtube.com/embed/${v.embed}" frameborder="0" allowfullscreen></iframe>
                            </div>
                            <div class="bg-black text-white font-semibold text-base px-3 py-2 mt-4 rounded-[40px] w-[290px] mx-auto" style="font-family: 'Inter', sans-serif; font-size: 16px; min-height: 32px;">${v.title}</div>
                            <p class="text-black text-sm mt-4 px-4" style="font-family: 'Inter', sans-serif; font-size: 14px; line-height: 18px;">${v.desc}</p>
                        </div>
                    `).join('')}
                </div>
                <button class="carousel-btn left absolute left-2 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-70 text-white w-12 h-12 rounded-full hover:bg-opacity-90 transition flex items-center justify-center" id="prevBtn" style="opacity: 0;"><i class="fas fa-chevron-left fa-lg"></i></button>
                <button class="carousel-btn right absolute right-2 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-70 text-white w-12 h-12 rounded-full hover:bg-opacity-90 transition flex items-center justify-center" id="nextBtn" style="opacity: 0;"><i class="fas fa-chevron-right fa-lg"></i></button>
                <div class="dots absolute bottom-2 left-1/2 transform -translate-x-1/2 flex space-x-2" id="dots">
                    ${videos.map((_, i) => `<span class="dot w-2 h-2 bg-gray-400 rounded-full cursor-pointer transition-colors ${i === 0 ? 'bg-black' : ''}" data-slide="${i}"></span>`).join('')}
                </div>
            </div>
        `;

        // Carousel logic
        let currentSlide = 0;
        const carousel = document.querySelector('.carousel');
        const carouselInner = document.getElementById('carouselInner');
        const dots = document.querySelectorAll('.dot');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');

        function showButtons() {
            prevBtn.style.opacity = '1';
            nextBtn.style.opacity = '1';
        }

        function hideButtons() {
            prevBtn.style.opacity = '0';
            nextBtn.style.opacity = '0';
        }

        function updateCarousel() {
            carouselInner.style.transform = `translateX(-${currentSlide * 100}%)`;
            dots.forEach((dot, i) => {
                dot.classList.toggle('bg-black', i === currentSlide);
                dot.classList.toggle('bg-gray-400', i !== currentSlide);
            });
        }

        prevBtn.addEventListener('click', () => {
            currentSlide = (currentSlide - 1 + videos.length) % videos.length;
            updateCarousel();
        });

        nextBtn.addEventListener('click', () => {
            currentSlide = (currentSlide + 1) % videos.length;
            updateCarousel();
        });

        dots.forEach((dot, i) => {
            dot.addEventListener('click', () => {
                currentSlide = i;
                updateCarousel();
            });
        });

        // Show buttons on mouse enter, hide on leave
        carousel.addEventListener('mouseenter', showButtons);
        carousel.addEventListener('mouseleave', hideButtons);

        // Swipe functionality
        let startX = 0;
        let endX = 0;
        carousel.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            showButtons();
            e.preventDefault();
        }, { passive: false });
        carousel.addEventListener('touchend', (e) => {
            endX = e.changedTouches[0].clientX;
            const diff = startX - endX;
            if (Math.abs(diff) > 100) {
                if (diff > 0) {
                    // swipe left, next
                    currentSlide = (currentSlide + 1) % videos.length;
                } else {
                    // swipe right, prev
                    currentSlide = (currentSlide - 1 + videos.length) % videos.length;
                }
                updateCarousel();
            }
            e.preventDefault();
        }, { passive: false });
    }
}

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

    // Update name overlay
    const nameText = data.personal_details[0].firstname + ' ' + data.personal_details[0].lastname + ' <img src="images/verified.png" alt="Verified" class="inline w-5 h-5">';
    const nameOverlay = document.querySelector('.name-text');
    if (nameOverlay) nameOverlay.innerHTML = nameText;

    // Update position
    const positionEl = document.querySelector('[data-api="position"]');
    if (positionEl) positionEl.innerHTML = data.personal_details[0].position;

    // Update company
    const companyEl = document.querySelector('[data-api="company"]');
    if (companyEl) companyEl.innerHTML = data.personal_details[0].company.trim();


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
    if (bioTitleEl) bioTitleEl.innerHTML = data.agent_bio[0].title;
    const bioDescEl = document.querySelector('[data-api="bio-description"]');
    if (bioDescEl) bioDescEl.innerHTML = data.agent_bio[0].description;

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
        bankDiv.className = 'bg-[#F4F4F4] rounded-[11px] p-6 cursor-pointer hover:bg-gray-200 transition-colors aspect-square';
        bankDiv.onclick = () => showBankModal(bank);
        const logoSrc = bank.bank_name.toLowerCase() === 'gcash' ? 'images/gcash-logo.png' : 'images/default-bank.png';
        bankDiv.innerHTML = `
            <div class="flex flex-col items-center text-center h-full">
                <img src="${logoSrc}" alt="${bank.bank_name}" class="w-20 h-20 object-contain">
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

    // Update social links phone #2 and email
    const socialPhoneLink = document.querySelector('.space-y-2 a[data-type="phone"]');
    if (socialPhoneLink) socialPhoneLink.href = 'tel:' + data.personal_details[0].mobile1;
    const socialEmailLink = document.querySelector('.space-y-2 a[data-type="email"]');
    if (socialEmailLink) socialEmailLink.href = 'mailto:' + data.personal_details[0].email;

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
    const modalTitle = document.getElementById('modalTitle');
    const modalContent = document.getElementById('modalContent');

    const logoSrc = bank.bank_name.toLowerCase() === 'gcash' ? 'images/gcash-logo.png' : 'images/default-bank.png';

    modalLogo.src = logoSrc;
    modalTitle.textContent = `${bank.bank_name} Bank Details`;

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


// Save to Contact button
const saveBtn = document.querySelector('img[alt="Save to Contact"]').closest('a');

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

// Save Contact button
const saveContactBtn = document.getElementById('saveContactBtn');
saveContactBtn.addEventListener('click', () => {
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

}