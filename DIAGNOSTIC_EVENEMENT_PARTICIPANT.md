# Diagnostic: Événement & Participant System

## Date: 2026-02-22

## Executive Summary
The event and participant system has **critical database schema mismatches** between entity definitions and the actual database structure. Several columns defined in entities are missing from the database, which will cause runtime errors.

---

## 🔴 CRITICAL ISSUES

### 1. Missing Database Columns

#### Problem: `lieu` column missing in `evenement` table
- **Entity Definition**: `Evenement.php` line 30 defines `lieu` field as `VARCHAR(255) nullable`
- **Database Schema**: Initial migration `Version20260206110431.php` does NOT create this column
- **Impact**: Any attempt to save/load events with location data will fail
- **Status**: ❌ NOT FIXED - No migration adds this column

#### Problem: `commentaire` column missing in `participation_evenement` table
- **Entity Definition**: `ParticipationEvenement.php` line 30 defines `commentaire` field as `TEXT nullable`
- **Database Schema**: Initial migration `Version20260206110431.php` does NOT create this column
- **Impact**: Participants cannot add comments when registering
- **Status**: ❌ NOT FIXED - No migration adds this column

### 2. Entity Relationship Mismatch

#### Problem: Incorrect `inversedBy` reference in `ParticipationEvenement`
- **Current**: `ParticipationEvenement.php` line 16 uses `inversedBy: 'participations'`
- **Expected**: Should use `inversedBy: 'participationsEvenements'`
- **Reason**: `Utilisateur.php` line 243 defines the collection as `$participationsEvenements`, not `$participations`
- **Impact**: Doctrine will fail to properly map the bidirectional relationship
- **Error Type**: Doctrine mapping exception at runtime

### 3. Missing `coach` Relationship in Evenement Entity

#### Problem: Database has `coach_id` but entity doesn't define the relationship
- **Database**: Migration creates `coach_id INT NOT NULL` with foreign key to `utilisateur`
- **Entity**: `Evenement.php` has NO `$coach` property or relationship
- **Impact**: 
  - Cannot assign coaches to events
  - Coach dashboard functionality is broken
  - Foreign key constraint violations when creating events
- **Status**: ❌ CRITICAL - Events cannot be created without a coach

---

## ⚠️ HIGH PRIORITY ISSUES

### 4. Anonymous User Creation Logic

**Location**: `EvenementController.php` lines 73-85

**Problem**: Creates fake users for demo purposes
```php
$utilisateur = new Utilisateur();
$utilisateur->setEmail('visiteur' . uniqid() . '@demo.local');
```

**Issues**:
- Creates database pollution with fake users
- Session-based tracking is fragile (cleared on browser close)
- No cleanup mechanism for anonymous users
- Not production-ready
- Violates data integrity principles

**Recommendation**: Implement proper authentication or guest participation tracking

### 5. Missing Validation

#### Event Creation (Controller lines 238-250)
- ❌ No validation that `dateDebut` < `dateFin`
- ❌ No validation that `dateDebut` is in the future
- ❌ No validation that `capaciteMax` > 0
- ❌ No form type with constraints
- ❌ Direct request parameter binding (security risk)

#### Participation Logic
- ❌ No check if event date has passed
- ❌ No validation of event status before allowing participation
- ❌ No rate limiting on participation attempts

### 6. Repository Query Issues

**Location**: `ParticipationEvenementRepository.php`

#### `findActiveParticipation()` method (lines 23-33)
```php
->andWhere('p.statut = :statut')
->setParameter('statut', 'confirmé')
```

**Problem**: Only searches for 'confirmé' status
- Misses 'en attente' participations
- User could register multiple times if first is 'en attente'
- Should check for both 'confirmé' AND 'en attente'

**Fix**:
```php
->andWhere('p.statut IN (:statuts)')
->setParameter('statuts', ['confirmé', 'en attente'])
```

### 7. Missing Repository Methods

The following methods are needed but not implemented:
- `findParticipantsByEvent(Evenement $event)` - Get all participants for an event
- `countConfirmedParticipants(Evenement $event)` - Count confirmed participants
- `findUpcomingEvents()` - Get events starting in the future
- `findPastEvents()` - Get completed events
- `findEventsByCoach(Utilisateur $coach)` - Get events by coach

---

## 🟡 MEDIUM PRIORITY ISSUES

### 8. Event Status Management

**Problem**: No automatic status updates
- Events remain 'ouvert' even when full
- Events remain 'ouvert' even after end date
- No background job to update statuses

**Missing Logic**:
- Auto-set status to 'complet' when `getNombreParticipants() >= capaciteMax`
- Auto-set status to 'terminé' when `dateFin < now()`
- Auto-set status to 'ouvert' when participant cancels and event was 'complet'

### 9. Capacity Check Race Condition

**Location**: `EvenementController.php` lines 104-108

**Problem**: Check-then-act pattern without transaction isolation
```php
if ($evenement->isComplet()) {
    // Race condition here!
}
$participation = new ParticipationEvenement();
$em->persist($participation);
```

**Scenario**: Two users register simultaneously for the last spot
- Both pass the `isComplet()` check
- Both get registered
- Event exceeds capacity

**Fix**: Use database-level constraints or pessimistic locking

### 10. Missing Form Types

No Symfony Form types defined for:
- `EvenementType` - Event creation/editing
- `ParticipationEvenementType` - Participation with comment

**Impact**:
- No CSRF protection
- No validation
- Manual request parameter handling (error-prone)
- No form rendering helpers in templates

### 11. Missing Cascade Operations

**Current**: `Evenement` entity line 48 has `orphanRemoval: true`
**Issue**: Migration also has `ON DELETE CASCADE` (redundant but acceptable)

**Missing**:
- No cascade on `Utilisateur` -> `ParticipationEvenement` relationship
- Deleting a user leaves orphaned participations

---

## 🟢 LOW PRIORITY ISSUES

### 12. Code Quality Issues

#### Inconsistent Method Naming
- `addParticipationsEvenement()` (Utilisateur.php line 1009)
- Should be `addParticipationEvenement()` (singular)

#### Missing Type Hints
- Some methods lack return type declarations
- Inconsistent nullable type usage

#### No Logging
- No audit trail for event creation/deletion
- No logging of participation changes
- No tracking of who modified events

### 13. Missing Features

- No email notifications for participants
- No reminder system before event starts
- No waiting list when event is full
- No participant limit per user
- No event categories/tags
- No search/filter functionality
- No pagination on event lists
- No image upload for events (field exists but not used)

---

## 📋 RECOMMENDED FIXES (Priority Order)

### IMMEDIATE (Must fix before system works)

1. **Create migration to add missing columns**
   ```sql
   ALTER TABLE evenement ADD lieu VARCHAR(255) DEFAULT NULL;
   ALTER TABLE participation_evenement ADD commentaire LONGTEXT DEFAULT NULL;
   ```

2. **Add coach relationship to Evenement entity**
   ```php
   #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'evenements')]
   #[ORM\JoinColumn(nullable: false)]
   private ?Utilisateur $coach = null;
   ```

3. **Fix ParticipationEvenement relationship**
   Change line 16 from:
   ```php
   #[ORM\ManyToOne(inversedBy: 'participations')]
   ```
   To:
   ```php
   #[ORM\ManyToOne(inversedBy: 'participationsEvenements')]
   ```

### SHORT TERM (Fix within 1 week)

4. Create Symfony Form types with validation
5. Fix `findActiveParticipation()` to check multiple statuses
6. Add proper authentication (remove anonymous user creation)
7. Add validation for date ranges and capacity
8. Implement automatic event status updates

### MEDIUM TERM (Fix within 1 month)

9. Add missing repository query methods
10. Implement transaction isolation for capacity checks
11. Add cascade delete for user participations
12. Create event status management service
13. Add logging and audit trails

### LONG TERM (Nice to have)

14. Email notification system
15. Waiting list functionality
16. Event search and filtering
17. Image upload for events
18. Participant management dashboard
19. Event analytics and reporting

---

## 🧪 TESTING RECOMMENDATIONS

### Unit Tests Needed
- `Evenement::isComplet()` method
- `Evenement::getPlacesRestantes()` method
- `Evenement::getNombreParticipants()` method

### Integration Tests Needed
- Event creation with coach assignment
- Participation registration flow
- Capacity limit enforcement
- Status transitions

### Manual Testing Checklist
- [ ] Create event with all fields including lieu
- [ ] Register participant with commentaire
- [ ] Fill event to capacity
- [ ] Try to register when full
- [ ] Cancel participation
- [ ] Delete event (check cascade)
- [ ] Create event as coach
- [ ] View coach dashboard

---

## 📊 DATABASE SCHEMA COMPARISON

### Expected (from entities) vs Actual (from migrations)

#### `evenement` table
| Column | Entity | Migration | Status |
|--------|--------|-----------|--------|
| id | ✅ | ✅ | OK |
| titre | ✅ | ✅ | OK |
| description | ✅ | ✅ | OK |
| date_debut | ✅ | ✅ | OK |
| date_fin | ✅ | ✅ | OK |
| **lieu** | ✅ | ❌ | **MISSING** |
| capacite_max | ✅ | ✅ | OK |
| type | ✅ | ✅ | OK |
| statut | ✅ | ✅ | OK |
| image | ✅ | ❌ | MISSING |
| created_at | ✅ | ✅ | OK |
| coach_id | ❌ | ✅ | **ORPHANED** |
| lien_session | ❌ | ✅ | ORPHANED |

#### `participation_evenement` table
| Column | Entity | Migration | Status |
|--------|--------|-----------|--------|
| id | ✅ | ✅ | OK |
| utilisateur_id | ✅ | ✅ | OK |
| evenement_id | ✅ | ✅ | OK |
| date_inscription | ✅ | ✅ | OK |
| statut | ✅ | ✅ | OK |
| **commentaire** | ✅ | ❌ | **MISSING** |

---

## 🔧 QUICK FIX SCRIPT

Run these commands to fix the immediate issues:

```bash
# 1. Create new migration
php bin/console make:migration

# 2. Edit the migration file to add:
# - ALTER TABLE evenement ADD lieu VARCHAR(255) DEFAULT NULL
# - ALTER TABLE evenement ADD image VARCHAR(255) DEFAULT NULL  
# - ALTER TABLE participation_evenement ADD commentaire LONGTEXT DEFAULT NULL

# 3. Run migration
php bin/console doctrine:migrations:migrate

# 4. Validate schema
php bin/console doctrine:schema:validate
```

---

## 📝 CONCLUSION

The event and participant system has a solid foundation but requires immediate fixes to function correctly. The most critical issues are:

1. **Database schema mismatches** - Will cause runtime errors
2. **Missing coach relationship** - Events cannot be created
3. **Incorrect ORM mappings** - Will cause Doctrine errors

Once these are fixed, the system should be functional for basic use cases, but will still need the medium and long-term improvements for production readiness.

**Estimated Fix Time**:
- Critical issues: 2-4 hours
- High priority: 1-2 days
- Medium priority: 1 week
- Low priority: 2-4 weeks

**Risk Level**: 🔴 HIGH - System will not work without immediate fixes
