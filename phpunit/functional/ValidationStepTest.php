<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2025 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

namespace tests\units;

use CommonITILValidation;
use Glpi\PHPUnit\Tests\Glpi\ValidationStepTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use ValidationStep;

class ValidationStepTest extends \DbTestCase
{
    use ValidationStepTrait;

    public function testDefaultValidationStepExistAtInstallation()
    {
        $this->assertGreaterThan(0, countElementsInTable(\ValidationStep::getTable()), 'At least one validation step should be created at installation');
        $this->assertEquals(1, $this->getInitialDefault()->fields['is_default'], 'A default validation step should be created at installation');
    }

    public function testTheLastValidationCannotBeDeletedIfItsTheOnlyOne()
    {
        assert(1 === countElementsInTable(\ValidationStep::getTable()), 'Test expects only one validation step at start');

        $default = $this->getInitialDefault();
        $this->assertFalse($default->delete(['id' => $default->getID()]), 'The last remaining validation step must not be deleted');
    }

    public function testAValidationStepCanBeDeletedIfItsNotTheLastOne()
    {
        $this->createItem(\ValidationStep::class, $this->getValidValidationStepData());

        $default = $this->getInitialDefault();
        $this->assertTrue($default->delete(['id' => $default->getID()]), 'The (initial) validation can be deleted, if it is not the last remaining one.');
    }

    public function testDefaultAttributeIsRemovedWhenSetToAnotherValidationStep()
    {
        // initial default is the default (@see testDefaultValidationStepExistAtInstallation() )
        // act : create a new validation step and set it as default
        $this->createItem(\ValidationStep::class, ['is_default' => 1] + $this->getValidValidationStepData());

        // assert
        $this->assertEquals(0, $this->getInitialDefault()->fields['is_default'], 'Previous default validation step should not be the default anymore.');
    }

    public function testDefaultAttributeIsSetToAnotherValidationStepWhenTheDefaultIsDeleted()
    {
        // arrange - create a non default validation step
        $new = $this->createItem(\ValidationStep::class, $this->getValidValidationStepData());
        $new_name = $new->fields['name'];

        // act - delete the default validation step
        $default = $this->getInitialDefault();
        assert(true === $default->delete(['id' => $default->getID()]), 'Test expect that the (initial) validation step can be deleted.');

        // assert - the previous non default validation step is the new default
        $new_default = getItemByTypeName(ValidationStep::class, $new_name);
        $this->assertEquals(1, $new_default->fields['is_default'], 'The previous non default validation step should be the new default.');
    }

    public function testNameAttributeIsMandatory()
    {
        // assert on add
        $vs = new \ValidationStep();
        $data = $this->getValidValidationStepData();
        unset($data['name']);

        $this->assertFalse($vs->add($data), 'A validation step without name should not be created');
        $this->hasSessionMessages(ERROR, ['The name field is mandatory']);

        // assert on update
        $vs = new \ValidationStep();
        $data = $this->getValidValidationStepData();
        $data['id'] = $this->getInitialDefault()->getID();
        $data['name'] = '';

        $this->assertFalse($vs->update($data), 'A validation step without name should not be updated');
        $this->hasSessionMessages(ERROR, ['The name field is mandatory']);
    }

    public function testMinimalRequiredValidationPercentAttributeIsMandatory()
    {
        // assert on add
        $vs = new \ValidationStep();
        $data = $this->getValidValidationStepData();
        unset($data['minimal_required_validation_percent']);
        $validation_error_message = sprintf(__s('The %s field is mandatory and must be beetween 0 and 100.'), $vs->getAdditionalField('minimal_required_validation_percent')['label'] ?? 'minimal_required_validation_percent');
        $this->assertFalse($vs->add($data), 'A validation step without minimal required validation percent should not be created');
        $this->hasSessionMessages(ERROR, [$validation_error_message]);

        // assert on update
        $vs = new \ValidationStep();
        $data = $this->getValidValidationStepData();
        $data['id'] = $this->getInitialDefault()->getID();
        $data['minimal_required_validation_percent'] = '';

        $this->assertFalse($vs->add($data), 'A validation step without minimal required validation percent should not be updated');
        $this->hasSessionMessages(ERROR, [$validation_error_message]);
    }

    public function testMinimalRequiredValidationPercentAttributeIsAPercentage()
    {
        $vs = new \ValidationStep();
        $data = $this->getValidValidationStepData();
        $expected_validation_error_message = sprintf(__s('The %s field is mandatory and must be beetween 0 and 100.'), $vs->getAdditionalField('minimal_required_validation_percent')['label'] ?? 'minimal_required_validation_percent');

        // act add - set a value higher than 100
        $data['minimal_required_validation_percent'] = 101;
        $this->assertFalse($vs->add($data), 'A validation step with "minimal_required_validation_percent" greater than 100 should not be created');
        $this->hasSessionMessages(ERROR, [$expected_validation_error_message]);

        // act add - set a value lower than 0
        $data['minimal_required_validation_percent'] = -1;
        $this->assertFalse($vs->add($data), 'A validation step with "minimal_required_validation_percent" lower than 0 should not be created');
        $this->hasSessionMessages(ERROR, [$expected_validation_error_message]);

        // act update - set a value higher than 100
        $vs = new \ValidationStep();
        $data = $this->getValidValidationStepData();
        $data['id'] = $this->getInitialDefault()->getID();
        $data['minimal_required_validation_percent'] = 101;
        $this->assertFalse($vs->update($data), 'A validation step with "minimal_required_validation_percent" greater than 100 should not be updated');
        $this->hasSessionMessages(ERROR, [$expected_validation_error_message]);

        // act update - set a value lower than 0
        $vs = new \ValidationStep();
        $data = $this->getValidValidationStepData();
        $data['id'] = $this->getInitialDefault()->getID();
        $data['minimal_required_validation_percent'] = -1;
        $this->assertFalse($vs->update($data), 'A validation step with "minimal_required_validation_percent" lower than 0 should not be updated');
        $this->hasSessionMessages(ERROR, [$expected_validation_error_message]);
    }

    #[DataProvider('getValidationStepStatusProvider')]
    public function testGetValidationStepStatus(int $mininal_required_validation_percent, array $validation_states, int $expected_status)
    {
        foreach ([\Ticket::class, \Change::class] as $itil_class) {
            $validation_step = $this->createValidationStep($mininal_required_validation_percent);
            // single itil_validation step with 100% required
            [$itil, $itils_validationstep] = $this->createITILSValidationStepWithValidations($validation_step, $validation_states, itil_classname: $itil_class);
            $result_status = $itil::getValidationStepClassName()::getITILValidationStepStatus($itils_validationstep->getID());

            $this->assertValidationStatusEquals($expected_status, $result_status);
        }
    }

    public function testGetITILValidationStepAchievementsOnSingleValidation(): void
    {
        foreach ([\Ticket::class, \Change::class] as $itil_class) {
            $vs = $this->createValidationStep(100);
            // accepted
            [$itil, $itil_validationstep] = $this->createITILSValidationStepWithValidations($vs, [CommonITILValidation::ACCEPTED], itil_classname: $itil_class);
            $achievements = $itil::getValidationStepClassName()::getITILValidationStepAchievements($itil_validationstep->getID());
            $this->assertEquals(100, $achievements[CommonITILValidation::ACCEPTED]);

            // refused
            [$itil, $itil_validationstep] = $this->createITILSValidationStepWithValidations($vs, [CommonITILValidation::REFUSED], itil_classname: $itil_class);
            $achievements = $itil::getValidationStepClassName()::getITILValidationStepAchievements($itil_validationstep->getID());
            $this->assertEquals(100, $achievements[CommonITILValidation::REFUSED]);

            // waiting
            [$itil, $itil_validationstep] = $this->createITILSValidationStepWithValidations($vs, [CommonITILValidation::WAITING], itil_classname: $itil_class);
            $achievements = $itil::getValidationStepClassName()::getITILValidationStepAchievements($itil_validationstep->getID());
            $this->assertEquals(100, $achievements[CommonITILValidation::WAITING]);
        }
    }

    public function testgetValidationStepAchievementsOnMultipleValidation(): void
    {
        foreach ([\Ticket::class, \Change::class] as $itil_class) {
            $vs = $this->createValidationStep(100);
            // 2 validations with same status
            [$itil, $itil_validationstep] = $this->createITILSValidationStepWithValidations($vs, [CommonITILValidation::ACCEPTED, CommonITILValidation::ACCEPTED], itil_classname: $itil_class);
            $achievements = $itil::getValidationStepClassName()::getITILValidationStepAchievements($itil_validationstep->getID());
            $this->assertEquals(100, $achievements[CommonITILValidation::ACCEPTED]);
            // test sum of % is 100
            $this->assertEquals(100, array_sum($achievements));

            // multiple validations with same status
            [$itil, $itil_validationstep] = $this->createITILSValidationStepWithValidations($vs, [CommonITILValidation::WAITING, CommonITILValidation::WAITING, CommonITILValidation::WAITING, CommonITILValidation::WAITING, CommonITILValidation::WAITING], itil_classname: $itil_class);
            $achievements = $itil::getValidationStepClassName()::getITILValidationStepAchievements($itil_validationstep->getID());
            $this->assertEquals(100, $achievements[CommonITILValidation::WAITING]);
            $this->assertEquals(100, array_sum($achievements));

            // multiple validations with different status
            [$itil, $itil_validationstep] = $this->createITILSValidationStepWithValidations($vs, [CommonITILValidation::WAITING, CommonITILValidation::REFUSED], itil_classname: $itil_class);
            $achievements = $itil::getValidationStepClassName()::getITILValidationStepAchievements($itil_validationstep->getID());
            $this->assertEquals(50, $achievements[CommonITILValidation::WAITING]);
            $this->assertEquals(50, $achievements[CommonITILValidation::REFUSED]);
            $this->assertEquals(100, array_sum($achievements));

            [$itil, $itil_validationstep] = $this->createITILSValidationStepWithValidations($vs, [CommonITILValidation::WAITING, CommonITILValidation::REFUSED, CommonITILValidation::ACCEPTED], itil_classname: $itil_class);
            $achievements = $itil::getValidationStepClassName()::getITILValidationStepAchievements($itil_validationstep->getID());
            $this->assertEquals(33, $achievements[CommonITILValidation::REFUSED]);
            $this->assertEquals(34, $achievements[CommonITILValidation::ACCEPTED]);
            $this->assertEquals(33, $achievements[CommonITILValidation::WAITING]);

            [$itil, $itil_validationstep] = $this->createITILSValidationStepWithValidations($vs, [CommonITILValidation::REFUSED, CommonITILValidation::REFUSED, CommonITILValidation::ACCEPTED], itil_classname: $itil_class);
            $achievements = $itil::getValidationStepClassName()::getITILValidationStepAchievements($itil_validationstep->getID());
            $this->assertEquals(67, $achievements[CommonITILValidation::REFUSED]);
            $this->assertEquals(33, $achievements[CommonITILValidation::ACCEPTED]);
            $this->assertEquals(0, $achievements[CommonITILValidation::WAITING]);

            // 4 validations with different status
            [$itil, $itil_validationstep] = $this->createITILSValidationStepWithValidations($vs, [CommonITILValidation::WAITING, CommonITILValidation::WAITING, CommonITILValidation::REFUSED, CommonITILValidation::ACCEPTED], itil_classname: $itil_class);
            $achievements = $itil::getValidationStepClassName()::getITILValidationStepAchievements($itil_validationstep->getID());
            $this->assertEquals(50, $achievements[CommonITILValidation::WAITING]);
            $this->assertEquals(25, $achievements[CommonITILValidation::REFUSED]);
            $this->assertEquals(25, $achievements[CommonITILValidation::ACCEPTED]);

            // 5 validations with different status
            [$itil, $itil_validationstep] = $this->createITILSValidationStepWithValidations($vs, [CommonITILValidation::REFUSED, CommonITILValidation::REFUSED, CommonITILValidation::REFUSED, CommonITILValidation::ACCEPTED, CommonITILValidation::ACCEPTED], itil_classname: $itil_class);
            $achievements = $itil::getValidationStepClassName()::getITILValidationStepAchievements($itil_validationstep->getID());
            $this->assertEquals(60, $achievements[CommonITILValidation::REFUSED]);
            $this->assertEquals(40, $achievements[CommonITILValidation::ACCEPTED]);
            $this->assertEquals(100, array_sum($achievements));
        }
    }

    public function testItilValidationStepIsRemovedWhenValidationIsDeleted(): void
    {
        foreach ([\Ticket::class, \Change::class] as $itil_class) {
            // arrange - create a validation (+ an itils_validationstep)
            $vs = $this->createValidationStep(100);
            [$itil, $itil_validationstep] = $this->createITILSValidationStepWithValidations($vs, [CommonITILValidation::ACCEPTED], itil_classname: $itil_class);
            $itil_validationstep_id = $itil_validationstep->getID();

            $validation = $itil::getValidationClassInstance();
            $validation_exists = $validation->getFromDBByCrit([$itil::getForeignKeyField() => $itil->getID(), 'itils_validationsteps_id' => $itil_validationstep_id]);
            assert($validation_exists);

            // act - delete the validation
            assert(true === $validation->delete(['id' => $validation->getID()]), 'The validation should be deleted');

            // assert - the itils_validationstep is deleted
            $this->assertFalse($itil_validationstep->getFromDB($itil_validationstep_id), 'The ITIL validation step should not in database');
        }
    }
    public function testItilValidationStepIsRemovedWhenValidationIsUpdated(): void
    {
        foreach ([\Ticket::class, \Change::class] as $itil_class) {
            // arrange - create a validation (+ an itils_validationstep)
            $vs = $this->createValidationStep(100);
            [$itil, $itil_validationstep] = $this->createITILSValidationStepWithValidations($vs, [CommonITILValidation::ACCEPTED], itil_classname: $itil_class);
            $first_itil_validationstep_id = $itil_validationstep->getID();

            $validation = $itil::getValidationClassInstance();
            $validation_exists = $validation->getFromDBByCrit([$itil::getForeignKeyField() => $itil->getID(), 'itils_validationsteps_id' => $first_itil_validationstep_id]);
            assert($validation_exists);

            // act - update the validation to use another validation step
            $new_vs = $this->createValidationStep(100);
            assert(true === $validation->update(['id' => $validation->getID(), '_validationsteps_id' => $new_vs->getID()]));

            // assert - the itils_validationstep is deleted
            $this->assertFalse($itil_validationstep->getFromDB($first_itil_validationstep_id), 'The ITIL validation step should not in database');
        }
    }

    public function testGetValidationStepClassName(): void
    {
        $this->assertNull(\Problem::getValidationStepClassName());
        $this->assertEquals(\TicketValidationStep::class, \Ticket::getValidationStepClassName());
        $this->assertEquals(\ChangeValidationStep::class, \Change::getValidationStepClassName());
    }

    public function testGetValidationStepInstance(): void
    {
        $this->assertNull(\Problem::getValidationStepInstance());
        $this->assertInstanceOf(\TicketValidationStep::class, \Ticket::getValidationStepInstance());
        $this->assertInstanceOf(\ChangeValidationStep::class, \Change::getValidationStepInstance());
    }

    // -- providers
    public static function getValidationStepStatusProvider(): array
    {
        /**
         * Array with
         * 0 : mininal_required_validation_percent
         * 1 : [Validation status, ...]
         * 2 : expected ValidationStep status
         */
        return [
            // ---- Refused validation step
            // 1 validation
            [100, [CommonITILValidation::REFUSED], CommonITILValidation::REFUSED],
            // 2 validations
            [100, [CommonITILValidation::REFUSED, CommonITILValidation::REFUSED], CommonITILValidation::REFUSED],
            [100, [CommonITILValidation::REFUSED, CommonITILValidation::WAITING], CommonITILValidation::REFUSED],
            [100, [CommonITILValidation::REFUSED, CommonITILValidation::ACCEPTED], CommonITILValidation::REFUSED],
            [50, [CommonITILValidation::REFUSED, CommonITILValidation::REFUSED], CommonITILValidation::REFUSED],
            [80, [CommonITILValidation::REFUSED, CommonITILValidation::WAITING], CommonITILValidation::REFUSED],
            [80, [CommonITILValidation::REFUSED, CommonITILValidation::ACCEPTED], CommonITILValidation::REFUSED],
            // 3 validations - 100 %
            [100, [CommonITILValidation::REFUSED, CommonITILValidation::ACCEPTED, CommonITILValidation::ACCEPTED], CommonITILValidation::REFUSED],
            [100, [CommonITILValidation::REFUSED, CommonITILValidation::WAITING, CommonITILValidation::WAITING], CommonITILValidation::REFUSED],
            // 3 validations - x/3 limit
            [67, [CommonITILValidation::REFUSED, CommonITILValidation::REFUSED, CommonITILValidation::ACCEPTED], CommonITILValidation::REFUSED],
            [34, [CommonITILValidation::REFUSED, CommonITILValidation::REFUSED, CommonITILValidation::ACCEPTED], CommonITILValidation::REFUSED],

            // --- Accepted validation step
            [100, [CommonITILValidation::ACCEPTED], CommonITILValidation::ACCEPTED],
            // 2 validations
            [100, [CommonITILValidation::ACCEPTED, CommonITILValidation::ACCEPTED], CommonITILValidation::ACCEPTED],
            [75, [CommonITILValidation::ACCEPTED, CommonITILValidation::ACCEPTED], CommonITILValidation::ACCEPTED],
            [50, [CommonITILValidation::ACCEPTED, CommonITILValidation::REFUSED], CommonITILValidation::ACCEPTED],
            [50, [CommonITILValidation::ACCEPTED, CommonITILValidation::WAITING], CommonITILValidation::ACCEPTED],
            [40, [CommonITILValidation::ACCEPTED, CommonITILValidation::REFUSED], CommonITILValidation::ACCEPTED],
            // 3 validations
            [100, [CommonITILValidation::ACCEPTED, CommonITILValidation::ACCEPTED, CommonITILValidation::ACCEPTED], CommonITILValidation::ACCEPTED],
            [66, [CommonITILValidation::ACCEPTED, CommonITILValidation::ACCEPTED, CommonITILValidation::WAITING], CommonITILValidation::ACCEPTED],
            [33, [CommonITILValidation::ACCEPTED, CommonITILValidation::REFUSED, CommonITILValidation::REFUSED], CommonITILValidation::ACCEPTED],
            [20, [CommonITILValidation::ACCEPTED, CommonITILValidation::WAITING, CommonITILValidation::WAITING], CommonITILValidation::ACCEPTED],

            // --- Waiting validation step
            [100, [CommonITILValidation::WAITING], CommonITILValidation::WAITING],

            [100, [CommonITILValidation::WAITING, CommonITILValidation::WAITING], CommonITILValidation::WAITING],
            [100, [CommonITILValidation::WAITING, CommonITILValidation::ACCEPTED], CommonITILValidation::WAITING],
            [40, [CommonITILValidation::WAITING, CommonITILValidation::REFUSED], CommonITILValidation::WAITING],

            [100, [CommonITILValidation::WAITING, CommonITILValidation::WAITING, CommonITILValidation::WAITING], CommonITILValidation::WAITING],
            [75, [CommonITILValidation::WAITING, CommonITILValidation::WAITING, CommonITILValidation::WAITING], CommonITILValidation::WAITING],
            [66, [CommonITILValidation::WAITING, CommonITILValidation::WAITING, CommonITILValidation::ACCEPTED], CommonITILValidation::WAITING],
            [66, [CommonITILValidation::WAITING, CommonITILValidation::WAITING, CommonITILValidation::REFUSED], CommonITILValidation::WAITING],

            // 5 validations -
            [20, [CommonITILValidation::WAITING, CommonITILValidation::WAITING, CommonITILValidation::WAITING, CommonITILValidation::ACCEPTED, CommonITILValidation::REFUSED], CommonITILValidation::ACCEPTED],
            [20, [CommonITILValidation::WAITING, CommonITILValidation::WAITING, CommonITILValidation::WAITING, CommonITILValidation::WAITING, CommonITILValidation::REFUSED], CommonITILValidation::WAITING],
            [20, [CommonITILValidation::WAITING, CommonITILValidation::REFUSED, CommonITILValidation::REFUSED, CommonITILValidation::REFUSED, CommonITILValidation::REFUSED], CommonITILValidation::WAITING],
            [40, [CommonITILValidation::WAITING, CommonITILValidation::REFUSED, CommonITILValidation::REFUSED, CommonITILValidation::REFUSED, CommonITILValidation::REFUSED], CommonITILValidation::REFUSED],
            [40, [CommonITILValidation::WAITING, CommonITILValidation::WAITING, CommonITILValidation::REFUSED, CommonITILValidation::REFUSED, CommonITILValidation::REFUSED], CommonITILValidation::WAITING],
            [40, [CommonITILValidation::ACCEPTED, CommonITILValidation::ACCEPTED, CommonITILValidation::REFUSED, CommonITILValidation::REFUSED, CommonITILValidation::REFUSED], CommonITILValidation::ACCEPTED],

            // --- special cases 0% required : one ACCEPTED -> ACCEPTED else one REFUSED -> REFUSED, else WAITING
            [0, [CommonITILValidation::WAITING], CommonITILValidation::WAITING],
            [0, [CommonITILValidation::ACCEPTED], CommonITILValidation::ACCEPTED],
            [0, [CommonITILValidation::REFUSED], CommonITILValidation::REFUSED],
            [0, [CommonITILValidation::WAITING, CommonITILValidation::REFUSED], CommonITILValidation::REFUSED],
            [0, [CommonITILValidation::WAITING, CommonITILValidation::ACCEPTED], CommonITILValidation::ACCEPTED],
            [0, [CommonITILValidation::ACCEPTED, CommonITILValidation::REFUSED], CommonITILValidation::ACCEPTED],
            [0, [CommonITILValidation::ACCEPTED, CommonITILValidation::REFUSED, CommonITILValidation::WAITING], CommonITILValidation::ACCEPTED],
        ];
    }

    // --- helpers
    private function getInitialDefault(): \ValidationStep
    {
        return getItemByTypeName(\ValidationStep::class, 'Validation');
    }
}
