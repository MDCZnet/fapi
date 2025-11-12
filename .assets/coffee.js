let coffeeCount = 0;

function createCoffeeItem(number) {
    const coffeeItem = document.createElement('div');
    coffeeItem.className = 'coffee-item';
    coffeeItem.innerHTML = `
        <div class="coffee-header">
            <label>${number}. Káva:</label>
            ${number > 1 ? '<a href="#" class="removeCoffee">odebrat</a>' : ''}
        </div>
        <div class="radio-group">
            <div class="radio-section">
                <label><input type="radio" name="sugar_${number}" value="bez_cukru" checked> bez cukru</label>
                <label><input type="radio" name="sugar_${number}" value="s_cukrem"> s cukrem</label>
            </div>
            <div class="radio-section">
                <label><input type="radio" name="milk_${number}" value="bez_mleka" checked> bez mléka</label>
                <label><input type="radio" name="milk_${number}" value="s_mlekem"> s mlékem</label>
            </div>
        </div>
    `;
    
    if (number > 1) {
        const removeLink = coffeeItem.querySelector('.removeCoffee');
        removeLink.addEventListener('click', function(e) {
            e.preventDefault();
            coffeeItem.remove();
            coffeeCount--;
            renumberCoffees();
            
            if (coffeeCount < 4) {
                document.getElementById('addCoffee').style.display = 'block';
            }
        });
    }
    
    return coffeeItem;
}

function renumberCoffees() {
    const coffeeItems = document.querySelectorAll('.coffee-item');
    coffeeItems.forEach((item, index) => {
        const number = index + 1;
        const label = item.querySelector('.coffee-header label');
        label.textContent = `${number}. Káva:`;
        
        const sugarRadios = item.querySelectorAll(`input[name^="sugar_"]`);
        sugarRadios.forEach(radio => radio.name = `sugar_${number}`);
        
        const milkRadios = item.querySelectorAll(`input[name^="milk_"]`);
        milkRadios.forEach(radio => radio.name = `milk_${number}`);
    });
}

const coffeeContainer = document.getElementById('coffeeContainer');
coffeeCount++;
coffeeContainer.appendChild(createCoffeeItem(coffeeCount));

document.getElementById('addCoffee').addEventListener('click', function(e) {
    e.preventDefault();
    
    if (coffeeCount >= 4) {
        return;
    }
    
    coffeeCount++;
    coffeeContainer.appendChild(createCoffeeItem(coffeeCount));
    
    if (coffeeCount >= 4) {
        this.style.display = 'none';
    }
});